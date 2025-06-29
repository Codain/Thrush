<?php
	/**
	 * Thrush Framework
	 *
	 * MIT License
	 *
	 * Copyright (c) 2004 Romain de Bossoreille
	 *
	 * Permission is hereby granted, free of charge, to any person obtaining 
	 * a copy of this software and associated documentation files (the 
	 * "Software"), to deal in the Software without restriction, including 
	 * without limitation the rights to use, copy, modify, merge, publish, 
	 * distribute, sublicense, and/or sell copies of the Software, and to 
	 * permit persons to whom the Software is furnished to do so, subject to 
	 * the following conditions:
	 *
	 * The above copyright notice and this permission notice (including the 
	 * next paragraph) shall be included in all copies or substantial portions 
	 * of the Software.
	 *
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS 
	 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF 
	 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. 
	 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY 
	 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, 
	 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
	 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	 *
	 * \copyright  Copyright (c) 2004 Romain de Bossoreille
	 * \license   https://opensource.org/licenses/MIT     MIT License
	 */
	 
	require_once __DIR__.'/Cache.php';
	require_once __DIR__.'/Exception.php';
	
	/**
	* Query a URL or read from Cache to get data
	*/
	class Thrush_WebPageLoader
	{
		/**
		* Default mode:
		*   - Retrieve from cache if available
		*   - Otherwise, always query the host (and store in cache the result if enabled)
		*/
		const MODE_DEFAULT = 0;
		
		/**
		* Cache only:
		*   - Retrieve from cache if available
		*   - Otherwise, always throw an exception
		*/
		const MODE_CACHE_ONLY = 1;
		
		/**
		* Default mode with limits:
		*   - Retrieve from cache if available
		*   - Otherwise, if the limits are not reached, query the host (and store in cache the result if enabled)
		*   - Otherwise, always throw an exception
		*/
		const MODE_BUDGET = 2;
		
		/**
		* Always query mode:
		*   - Always query the host (and store in cache the result if enabled)
		*/
		const MODE_QUERY_ONLY = 3;
		
		/**
		* Get the mode from a callback
		*/
		const MODE_CALLBACK = 4;
		
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		protected $cache = null;
		
		/**
		* string Name of the cache to use
		*/
		protected $type = '';
		
		/**
		* int Timeout when loading an URL for the connect phase (in ms, see CURLOPT_CONNECTTIMEOUT_MS)
		*/
		protected $urlConnectTimeout = 5000; // To do: setter
		
		/**
		* int Timeout when loading an URL for the whole operation (in ms, see CURLOPT_TIMEOUT_MS)
		*/
		protected $urlTimeout = 10000; // To do: setter
		
		/**
		* DateTime Date of the last retrieved data.
		*/
		protected $lastDate = null;
		
		/**
		* string Key of the last data retrieved.
		*/
		protected $lastDateKey = null;
		
		/**
		* int The budgetted number of calls allowed when in MODE_BUDGET mode.
		*/
		protected $budget = 10;
		
		/**
		* int The set mode for this instance.
		*/
		protected $mode = self::MODE_DEFAULT;
		
		/**
		* int A counter of calls for this instance.
		*/
		protected $callCounter = 0;
		
		/**
		* CurlHandle The baseline cUrl ressource used to make calls.
		*/
		protected $curl = null;
		
		/**
		* callable The callback to be called to determine dynamically the mode of this instance.
		*/
		protected $modeCallback = null;
		
		/**
		* Constructor.
		*
		* \param Thrush_Cache $cache
		*   Nominatim requires use of a Cache
		* \param string $type
		*   Name of the cache to use
		* \param string $websiteName
		*   Website name, used in HTTP User-Agent attribute
		* \param string $websiteURL
		*   Website URL, used in HTTP User-Agent attribute
		*
		* \throws Thrush_Exception If no cache is provided
		* \throws Thrush_Exception If the object cannot be initialized
		*/
		function __construct(Thrush_Cache $cache, string $type, string $websiteName, string $websiteURL)
		{
			// Setting up the cache
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Nominatim server');
			}
			
			$this->cache = $cache;
			$this->type = $type;
			$this->setModeDefault();
			
			// Initialize the cUrl ressource
			$this->curl = curl_init();
			
			if($this->curl === false)
			{
				throw new Thrush_Exception('Error', 'Unable to initialize a new cURL ressource');
			}
			
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($this->curl, CURLOPT_USERAGENT, $websiteName.' ('.$websiteURL.')');
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT_MS, $this->urlConnectTimeout);
			curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, $this->urlTimeout);
		}
		
		/**
		* Destructor.
		*/
		function __destruct()
		{
			if(!is_null($this->curl))
			{
				curl_close($this->curl);
			}
		}
		
		/**
		* Set mode to MODE_DEFAULT.
		*
		* \see getMode
		*/
		public function setModeDefault()
		{
			$this->mode = self::MODE_DEFAULT;
			$this->budget = 0;
			$this->modeCallback = null;
		}
		
		/**
		* Set mode to MODE_CACHE_ONLY.
		*
		* \see getMode
		*/
		public function setModeCacheOnly()
		{
			$this->mode = self::MODE_CACHE_ONLY;
			$this->budget = 0;
			$this->modeCallback = null;
		}
		
		/**
		* Set mode to MODE_BUDGET.
		*
		* \param int $budget
		*   Number of tokens when in DefaultWithLimitsMode (optional, -1 to use default value or keep existing value)
		*
		* \see getMode
		*/
		public function setModeDefaultWithBudget(int $budget)
		{
			$this->mode = self::MODE_BUDGET;
			$this->budget = ($budget>=0?$budget:$this->budget);
			$this->modeCallback = null;
		}
		
		/**
		* Set mode to MODE_QUERY_ONLY.
		*
		* \see getMode
		*/
		public function setModeAlwaysRefresh()
		{
			$this->mode = self::MODE_QUERY_ONLY;
			$this->budget = 0;
			$this->modeCallback = null;
		}
		
		/**
		* Set mode to MODE_CALLBACK.
		*
		* The callback shall have the following arguments (see documentation for loadURL to get the definition):
		* \verbatim
		* string $type, string $url, ?array $postData, ?string $key, int $life, ?array $additionalHeaders
		* \endverbatim
		*
		* The callback shall return as an \c int the current mode.
		*
		* \param callable $callback
		*   The callable to be called
		*
		* \see getMode
		*/
		public function setModeCallback(callable $callback)
		{
			$this->mode = self::MODE_CALLBACK;
			$this->budget = 0;
			$this->modeCallback = $callback;
		}
		
		/**
		* Get mode.
		*
		* \return int
		*
		* \see setModeDefault
		* \see setModeCacheOnly
		* \see setModeDefaultWithBudget
		* \see setModeAlwaysRefresh
		* \see setModeCallback
		*/
		public function getMode()
		{
			return $this->mode;
		}
		
		/**
		* Consume a token from the budget if available and returns \c true, otherwise returns \c false.
		*
		* \return bool
		*/
		private function trySpendOneToken()
		{
			$this->budget--;
			
			return ($this->budget>=0);
		}
		
		/**
		* Retrieve data on usage.
		*
		* \return array
		*/
		public function getStatistics()
		{
			return array(
				'type' => $this->type,
				'mode' => $this->mode,
				'tokensLeft' => max($this->budget, 0),
				'tokensMissing' => ($this->mode===self::MODE_BUDGET?max(-$this->budget, 0):'0'),
				'calls' => $this->callCounter
			);
		}
		
		/**
		* Get creation date of last data.
		* 
		* \return DateTime
		*   PHP DateTime
		*/
		public function getLastDate()
		{
			if(is_null($this->lastDate) && !is_null($this->type) && !is_null($this->lastDateKey))
			{
				$this->lastDate = new DateTime();
				$this->lastDate->setTimestamp($this->cache->getCreationTime($this->type, $this->lastDateKey));
			}
			
			return $this->lastDate;
		}
		
		/**
		* Set some HTTP Headers to be sent for any subsequent query.
		* The headers sent while calling loadURL will complement/override those headers.
		*
		* To add or change the header "xxx" and give the value "yyy": "xxx: "yyy" 
		* To remove the header "xxx": "xxx:"
		* 
		* \param array $baseHeaders
		*   Array of headers (e.g. array('Content-Length: 58') )
		*
		* \see loadURL()
		*/
		public function setHeaders(array $baseHeaders)
		{
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $baseHeaders);
		}
		
		/**
		* Fetch data from a given URL, either from Web or cache.
		* If the data is already in cache and cache has not expired, load data from cache.
		* If this cache type is disabled, \c life attribute is ignored.
		*
		* If a callback is provided, it will be called if data is loaded from URL and before saving. 
		* First item of the array shall be a callable PHP object, other items are optional to define callback arguments.
		* This array will be used as callback parameters, given that first item (callable object) will be replaced by a reference to the data.
		* Example 1: array('queryCallback', $var1, $var2) will call queryCallback(&$data, $var1, $var2)
		* Example 2: array(array($this, 'queryCallback'), $var1, $var2) will call $this->queryCallback(&$data, $var1, $var2)
		* 
		* \param string $url
		*   URL of the data to load
		* \param array $postData
		*   Data to send (will switch to POST mode if not null)
		* \param string $key
		*   Key associated to the data (null to generate one from URL)
		* \param int $life
		*   Cache life in seconds (or use Thrush_Cache constants)
		* \param string $pwd
		*   Password to encrypt data, if required
		* \param array $callback
		*   Optional array made of a callable and arguments to modify/analyse data before saving to cache
		* \param array $additionalHeaders
		*   Optional array of additional headers (e.g. array('Content-Length: 58') )
		* 
		* \return string
		*   Data
		*
		* \see exists()
		* \see load()
		* \see setHeaders()
		
		* \throws Thrush_Exception If external ressource cannot be reached
		* \throws Thrush_Cache_NoDataToLoadException If data shall be retrieved from cache but are not available.
		* \throws Thrush_HTTPException If HTTP code different from 200
		*/
		public function loadURL(string $url, ?array $postData, ?string $key, int $life=Thrush_Cache::LIFE_IMMORTAL, string $pwd='', array $callback=null, array $additionalHeaders=null)
		{
			// If no key is specified, we generate a hash using:
			//   - The URL
			//   - The data to be sent via POST
			if(is_null($key))
			{
				$key = md5($url.(is_null($postData)?'':serialize($postData)));
			}
			
			//echo '<span style="color: red;">CACHE: Loading type '.$type.' with URL '.$url.' (key '.$key.') and life '.$life.'</span><br />';
			
			// Determine whether we will need to launch query or to return cache
			// Here we work on a copy of the mode because we are going to change in some cases
			$launchQuery = false;
			$mode = $this->mode;
			
			// If mode is associated to a dwnamic callback, we call it
			if($mode === self::MODE_CALLBACK)
			{
				$mode = call_user_func_array($this->modeCallback, array($this->type, $url, $postData, $key, $life, $additionalHeaders));
			}
			
			$loop = true;
			while($loop)
			{
				$loop = false;
				switch($mode)
				{
					case self::MODE_QUERY_ONLY:
						$launchQuery = true;
						break;
					case self::MODE_BUDGET:
						if($this->cache->exists($this->type, $key, $life))
						{
							$mode = self::CacheOnlyMode;
						}
						else
						{
							if($this->trySpendOneToken())
							{
								$mode = self::AlwaysRefreshMode;
							}
							else
							{
								$mode = self::CacheOnlyMode;
							}
						}
						$loop = true;
						break;
					case self::MODE_CACHE_ONLY:
						if(!$this->cache->exists($this->type, $key, self::LIFE_IMMORTAL))
						{
							throw new Thrush_Cache_NoDataToLoadException();
						}
						break;
					case self::MODE_DEFAULT:
					default:
						if(!$this->cache->exists($this->type, $key, $life))
						{
							$launchQuery = true;
						}
						break;
				}
			}
			
			// Get Data from query or cache and return it
			if($launchQuery)
			{
				$this->callCounter++;
				
				// We ensure we have a cUrl ressource defined
				// We will use a copy of it in order to not impact the already defined optons
				if(is_null($this->curl))
				{
					throw new Thrush_Exception('Error', 'cURL ressource not initialized');
				}
				
				$curl = curl_copy_handle($this->curl);
				
				if($curl === false)
				{
					throw new Thrush_Exception('Error', 'Unable to initialize a new cURL ressource');
				}
				
				try
				{
					// We set the destination URL to load
					curl_setopt($curl, CURLOPT_URL, $url);
					
					// If we have to be in POST mode
					if(!is_null($postData))
					{
						curl_setopt($curl, CURLOPT_POST, 1);
						curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
					}
					else
					{
						curl_setopt($curl, CURLOPT_POST, 0);
					}
					
					// If we have additional headers, we add them
					if(!is_null($additionalHeaders))
					{
						curl_setopt($curl, CURLOPT_HTTPHEADER, $additionalHeaders);
					}
					
					$data = curl_exec($curl);
					
					if(curl_errno($curl))
					{   
						throw new Thrush_CurlException($curl, $data);
					}
					
					$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					
					if($responseCode === 200)
					{
						// If a callback has been defined to modify data without saving, we call it.
						// First argument will be replaced by a reference to the data to modify/analyse.
						if(!is_null($callback))
						{
							$args = $callback;
							$args[0] =& $data;
							call_user_func_array($callback[0], $args);
						}
						
						// Save data only if requested
						if($this->cache->isEnabled($this->type))
						{
							if($this->cache->save($this->type, $key, $data, $pwd) === false)
							{
								throw new Thrush_Exception('Error', 'Unable to save the result');
							}
						}
						
						// Save last retrieved data
						$this->lastDate = new DateTime();
						$this->lastDateKey = $key;
						
						curl_close($curl);
						
						return $data;
					}
					else
					{
						throw new Thrush_CurlHttpException($curl, $data);
					}
				}
				catch(Throwable $e)
				{
					curl_close($curl);
					throw $e;
				}
			}
			else
			{
				return $this->cache->load($this->type, $key, $pwd);
			}
		}
		
		/**
		* Wrapper of the method loadURL to fetch data from a given URL, either from Web or cache, load as HTML and return as a PHP DOMDocument.
		* 
		* Refer to the documentation of the method loadURL for parameters and Exceptions.
		* 
		* \return DOMDocument
		*   DOMDocument Object
		*
		* \see loadURL()
		*/
		public function loadURLAsHTMLDOMDocument(string $url, ?array $postData, ?string $key, int $life=Thrush_Cache::LIFE_IMMORTAL, string $pwd='', array $callback=null, array $additionalHeaders=null)
		{
			// Because this class cannot decompress data, we force the header "Accept-Encoding" to "identity"
			if(is_null($additionalHeaders))
				$additionalHeaders = array();
			$additionalHeaders[] = 'Accept-Encoding: identity';
			
			// Load the URL
			$content = $this->loadURL($url, $postData, $key, $life, $pwd, $callback, $additionalHeaders);
			
			// Transform the answer into a DOMDocument
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			libxml_clear_errors();
			$dom->recover = true;
			$dom->loadHTML($content);
			
			return $dom;
		}
		
		/**
		* Wrapper of the method loadURL to fetch data from a given URL, either from Web or cache, load as XML and return as a PHP DOMDocument.
		* 
		* Refer to the documentation of the method loadURL for parameters and Exceptions.
		* 
		* \return DOMDocument
		*   DOMDocument Object
		*
		* \see loadURL()
		*/
		public function loadURLAsXMLDOMDocument(string $url, ?array $postData, ?string $key, int $life=Thrush_Cache::LIFE_IMMORTAL, string $pwd='', array $callback=null, array $additionalHeaders=null)
		{
			// Because this class cannot decompress data, we force the header "Accept-Encoding" to "identity"
			if(is_null($additionalHeaders))
				$additionalHeaders = array();
			$additionalHeaders[] = 'Accept-Encoding: identity';
			
			// Load the URL
			$content = $this->loadURL($url, $postData, $key, $life, $pwd, $callback, $additionalHeaders);
			
			// Transform the answer into a DOMDocument
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			libxml_clear_errors();
			$dom->recover = true;
			$dom->loadXML($content);
			
			return $dom;
		}
	}
?>