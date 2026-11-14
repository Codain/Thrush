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
	
	require_once __DIR__.'/Crypt.php';
	require_once __DIR__.'/Exception.php';
	
	/**
	* This enumeration contains the different modes available to use a Cache.
	*/
	enum Thrush_Cache_Mode: int
	{
		/**
		* If the requested data are already stored and are not outdated, it will be readback. Otherwise an external call will be made.
		*/
		case DefaultMode = 0;
		
		/**
		* If the requested data are already stored no matter wether it is outdated, it will be readback. Otherwise an Exception will be thrown.
		*/
		case CacheOnlyMode = 1;
		
		/**
		* Same as default mode but with a counter (semaphore) before switching to CacheOnlyMode.
		*/
		case DefaultWithLimitsMode = 2;
		
		/**
		* Always issue external call and save in cache.
		*/
		case AlwaysRefreshMode = 3;
		
		/**
		* Good practice to define the default value to guide users
		*/
		public const DEFAULT = self::DefaultMode;
	}
	
	/**
	* This class provides an abstraction to provide caching capabilities in order to store/retrieve data.
	*
	* Regarding external URL call, each type of cache can be configured with a defined mode (see Thrush_Cache_Mode).
	*/
	abstract class Thrush_Cache
	{
		/**
		* The ressource in the cache is considered to never expire
		*/
		public const int LIFE_IMMORTAL = -1;
		
		/**
		* The ressource in the cache is considered to expire one hour after it was stored
		*/
		public const int LIFE_HOUR = 3600;
		
		/**
		* The ressource in the cache is considered to expire one day after it was stored
		*/
		public const int LIFE_DAY = 86400;
		
		/**
		* The ressource in the cache is considered to expire one week after it was stored
		*/
		public const int LIFE_WEEK = 604800;
		
		/**
		* The ressource in the cache is considered to expire two weeks after it was stored
		*/
		public const int LIFE_BIWEEK = 1296000;
		
		/**
		* The ressource in the cache is considered to expire one month after it was stored
		*/
		public const int LIFE_MONTH = 2635200;
		
		/**
		* Deprecated, use Thrush_Cache_Mode instead
		*/
		public const Thrush_Cache_Mode DefaultMode = Thrush_Cache_Mode::DefaultMode;
		
		/**
		* Deprecated, use Thrush_Cache_Mode instead
		*/
		public const Thrush_Cache_Mode CacheOnlyMode = Thrush_Cache_Mode::CacheOnlyMode;
		
		/**
		* Deprecated, use Thrush_Cache_Mode instead
		*/
		public const Thrush_Cache_Mode DefaultWithLimitsMode = Thrush_Cache_Mode::DefaultWithLimitsMode;
		
		/**
		* Deprecated, use Thrush_Cache_Mode instead
		*/
		public const Thrush_Cache_Mode AlwaysRefreshMode = Thrush_Cache_Mode::AlwaysRefreshMode;
		
		/**
		* string Website name (used for user-agent HTTP attribute).
		*/
		protected string $websiteName = '';
		
		/**
		* string Website URL (used for user-agent HTTP attribute).
		*/
		protected string $websiteURL = '';
		
		/**
		* DateTime Date of the last retrieved data.
		*/
		protected ?DateTime $lastDate = null;
		
		/**
		* string Type of the last data retrieved.
		*/
		protected ?string $lastDateType = null;
		
		/**
		* string Key of the last data retrieved.
		*/
		protected ?string $lastDateKey = null;
		
		/**
		* string Hash to check password. Must be 16 characters.
		*/
		protected string $pwdCheck = '!7+t6]3FVf7;xK#X';
		
		/**
		* array Set of modes and semaphore for each type.
		*/
		protected array $typeModes = array();
		
		/**
		* Thrush_Cache_Mode Default mode.
		*/
		protected Thrush_Cache_Mode $defaultMode = Thrush_Cache_Mode::DefaultMode;
		
		/**
		* int Default number of tokens when in DefaultWithLimitsMode.
		*/
		protected int $defaultSemaphore = 10;
		
		/**
		* bool Default value if a cache is neither enabled nor disabled.
		*/
		protected bool $defaultEnabled = false;
		
		/**
		* int Timestamp to evaluate item life
		*/
		protected ?int $time = null;
		
		/**
		* int Timeout when loading an URL for the connect phase (in ms, see CURLOPT_CONNECTTIMEOUT_MS)
		*/
		protected int $urlConnectTimeout = 5000;
		
		/**
		* int Timeout when loading an URL for the whole operation (in ms, see CURLOPT_TIMEOUT_MS)
		*/
		protected int $urlTimeout = 10000;
		
		
		
		
		/**
		* Constructor.
		* For performance reasons it is highly recommended for \c $root to be an absolute path (see \c realpath()).
		* 
		* \param string $websiteName
		*   Website name, used in HTTP User-Agent attribute
		* \param string $websiteURL
		*   Website URL, used in HTTP User-Agent attribute
		*/
		function __construct(string $websiteName, string $websiteURL)
		{
			$this->websiteName = $websiteName;
			$this->websiteURL = $websiteURL;
			
			// Initialise timestamp for all date evaluations
			$this->time = time();
		}
		
		/**
		* Destructor.
		*/
		function __destruct()
		{
			foreach($this->typeModes as $typeMode)
			{
				if(!is_null($typeMode[4]))
				{
					curl_close($typeMode[4]);
				}
			}
		}
		
		
		
		
		/**
		* Initialize a given type of cache.
		*
		* \param string $type
		*   Name of the cache to use
		*
		* \throws Thrush_Exception If unable to initialize a cURL ressource
		*/
		protected function initMode(string $type)
		{
			$this->typeModes[$type] = array(
				$this->defaultMode, 
				$this->defaultSemaphore, 
				0,  // Number of call operations
				$this->defaultEnabled,
				null, // cUrl ressource
				0, 	// Number of save operations
				0 	// Number of load operations
				);
		}
		
		/**
		* Set mode for a given type of cache.
		*
		* \deprecated This method will be removed in a following release, use the class WebPageLoader instead
		*
		* \param Thrush_Cache_Mode $mode
		*   Mode to assign
		* \param int $semaphore
		*   Number of tokens when in DefaultWithLimitsMode (optional, -1 to use default value or keep existing value)
		* \param string $type
		*   Name of the cache to use (optional)
		*
		* \see getMode
		*/
		public function setMode(Thrush_Cache_Mode $mode, int $semaphore=-1, string $type='')
		{
			if($type === '')
			{
				foreach($this->typeModes as $k => &$v)
				{
					$v[0] = $mode;
					
					if($semaphore >= 0)
						$v[1] = $semaphore;
				}
				
				$this->defaultMode = $mode;
			}
			else
			{
				if(!array_key_exists($type, $this->typeModes))
				{
					$this->initMode($type);
				}
				
				$this->typeModes[$type][0] = $mode;
				$this->typeModes[$type][1] = ($semaphore>=0?$semaphore:$this->defaultSemaphore);
			}
		}
		
		/**
		* Get mode for a given type of cache.
		*
		* \param string $type
		*   Name of the cache to use
		*
		* \return Thrush_Cache_Mode
		*
		* \see setMode
		*/
		protected function getMode(string $type): Thrush_Cache_Mode
		{
			if(!array_key_exists($type, $this->typeModes))
			{
				$this->initMode($type);
			}
			
			return $this->typeModes[$type][0];
		}
		
		/**
		* Consume a token if available and returns \c true, otherwise returns \c false.
		*
		* \param string $type
		*   Name of the cache to use
		*
		* \return bool
		*/
		protected function getSemaphore(string $type): bool
		{
			if(!array_key_exists($type, $this->typeModes))
			{
				$this->initMode($type);
			}
			
			$this->typeModes[$type][1]--;
			
			return ($this->typeModes[$type][1]>=0);
		}
		
		/**
		* Retrieve data on Cache usage.
		*
		* \return array
		*/
		public function getStatistics(): array
		{
			$ret = array();
			
			foreach($this->typeModes as $key => $values)
			{
				$ret[] = array(
					'type' => $key,
					'mode' => $values[0],
					'tokensLeft' => max($values[1], 0),
					'tokensMissing' => ($values[0]===Thrush_Cache_Mode::DefaultWithLimitsMode?max(-$values[1], 0):'0'),
					'calls' => $values[2],
					'savings' => $values[5],
					'loadings' => $values[6]
				);
			}
			
			return $ret;
		}
		
		
		
		
		/**
		* Allow to enable a cache type.
		*
		* \param string $type
		*   Name of the cache to use
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see disable()
		* \see isEnabled()
		*/
		public function enable(string $type): bool
		{
			if(!array_key_exists($type, $this->typeModes))
			{
				$this->initMode($type);
			}
			
			$this->typeModes[$type][3] = true;
			
			return true;
		}
		
		/**
		* Allow to disable a cache type.
		*
		* \param string $type
		*   Name of the cache to use
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see enable()
		* \see isEnabled()
		*/
		public function disable(string $type): bool
		{
			if(!array_key_exists($type, $this->typeModes))
			{
				$this->initMode($type);
			}
			
			$this->typeModes[$type][3] = false;
			
			return true;
		}
		
		/**
		* Get if a cache type is enabled or not.
		* 
		* \param string $type
		*   Name of the cache to use
		* 
		* \return bool
		*   True if enabled, false otherwise
		*
		* \see enable()
		* \see disable()
		* \see setDefaultEnabled()
		*/
		public function isEnabled(string $type): bool
		{
			if(!array_key_exists($type, $this->typeModes))
			{
				$this->initMode($type);
			}
			
			return $this->typeModes[$type][3];
		}
		
		/**
		* Get if a cache type is enabled or not.
		* 
		* \param bool $defaultEnabled
		*   Default value if a cache is neither enabled nor disabled
		* 
		* \see isEnabled()
		*/
		public function setDefaultEnabled(bool $defaultEnabled)
		{
			$this->defaultEnabled = $defaultEnabled;
		}
		
		
		
		
		/**
		* Get creation date of a stored data.
		* 
		* \return int|null
		*   Unix timestamp or null if the data is not found in the cache
		*/
		abstract public function getCreationTime(string $type, string $key): ?int;
		
		/**
		* Get creation date of last data.
		* 
		* \return DateTime
		*   PHP DateTime
		*/
		public function getLastDate(): DateTime
		{
			if(is_null($this->lastDate) && !is_null($this->lastDateType) && !is_null($this->lastDateKey))
			{
				$this->lastDate = new DateTime();
				$this->lastDate->setTimestamp($this->getCreationTime($this->lastDateType, $this->lastDateKey));
			}
			
			return $this->lastDate;
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
		* \deprecated This method will be removed in a following release, use the class WebPageLoader instead
		*
		* \param string $type
		*   Name of the cache to use
		* \param string $url
		*   URL of the data to load
		* \param array $postData
		*   Data to send (will switch to POST mode if not null)
		* \param string $key
		*   Key associated to the data (null to generate one from URL)
		* \param int $life
		*   Cache life in seconds
		* \param string $pwd
		*   Password to encrypt data, if required
		* \param array $callback
		*   Optional array made of a callback and arguments to modify/analyse data before saving to cache
		* \param array $additionalHeaders
		*   Optional array of additional headers (e.g. array('Content-Length: 58') )
		* 
		* \return string
		*   Data
		*
		* \see exists()
		* \see load()
		
		* \throws Thrush_Exception If external ressource cannot be reached
		* \throws Thrush_Cache_NoDataToLoadException If data shall be retrieved from cache but are not available.
		* \throws Thrush_HTTPException If HTTP code different from 200
		*/
		public function loadURLFromWebOrCache(string $type, string $url, array $postData=null, string $key=null, int $life=self::LIFE_IMMORTAL, string $pwd='', array $callback=null, array $additionalHeaders=null): string
		{
			if(is_null($key))
			{
				$key = md5($url.(is_null($postData)?'':serialize($postData)));
			}
			
			// Determine wether we willl need to launch query or to return cache
			$launchQuery = false;
			$mode = $this->getMode($type);
			
			$loop = true;
			while($loop)
			{
				$loop = false;
				switch($mode)
				{
					case Thrush_Cache_Mode::AlwaysRefreshMode:
						$launchQuery = true;
						break;
					case Thrush_Cache_Mode::DefaultWithLimitsMode:
						if($this->exists($type, $key, $life))
						{
							$mode = Thrush_Cache_Mode::CacheOnlyMode;
						}
						else
						{
							if($this->getSemaphore($type))
							{
								$mode = Thrush_Cache_Mode::AlwaysRefreshMode;
							}
							else
							{
								$mode = Thrush_Cache_Mode::CacheOnlyMode;
							}
						}
						$loop = true;
						break;
					case Thrush_Cache_Mode::CacheOnlyMode:
						if(!$this->exists($type, $key, self::LIFE_IMMORTAL))
						{
							throw new Thrush_Cache_NoDataToLoadException();
						}
						break;
					case Thrush_Cache_Mode::DefaultMode:
					default:
						if(!$this->exists($type, $key, $life))
						{
							$launchQuery = true;
						}
						break;
				}
			}
			
			// Get Data from query or cache and return it
			if($launchQuery)
			{
				$this->typeModes[$type][2]++;
				
				// If it is the first time we request to this type of cache, we initialize a cURL ressource
				if(is_null($this->typeModes[$type][4]))
				{
					$curl = curl_init();
					
					if($curl === false)
					{
						throw new Thrush_Exception('Error', 'Unable to initialize a new cURL ressource');
					}
					
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
					curl_setopt($curl, CURLOPT_USERAGENT, $this->websiteName.' ('.$this->websiteURL.')');
					curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $this->urlConnectTimeout);
					curl_setopt($curl, CURLOPT_TIMEOUT_MS, $this->urlTimeout);
					
					$this->typeModes[$type][4] = $curl;
				}
				else
				{
					$curl = $this->typeModes[$type][4];
				}
				
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
				else
				{
					curl_setopt($curl, CURLOPT_HTTPHEADER, array());
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
					if($this->isEnabled($type))
					{
						if($this->save($type, $key, $data, $pwd) === false)
						{
							throw new Thrush_Exception('Error', 'Unable to save the result');
						}
					}
					
					// Save last retrieved data
					$this->lastDate = new DateTime();
					$this->lastDateType = $type;
					$this->lastDateKey = $key;
					
					return $data;
				}
				else
				{
					throw new Thrush_CurlHttpException($curl, $data);
				}
			}
			else
			{
				return $this->load($type, $key, $pwd);
			}
		}
		
		/**
		* Automatically issue a 304 response code when appropriated before a content is generated.
		* If a data is available in cache and the client has a cached version and that version 
		* is up to date, send a 304 HTTP response code and return \c true.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param int $life
		*   Cache life in seconds
		* 
		* \return bool
		*   \c true if a 304 response code has been sent, \c false otherwise
		*/
		public function autoReplyIfNotModified(string $type, string $key, int $life=self::LIFE_IMMORTAL): bool
		{
			// If data exists in cache and is not expired...
			if($this->exists($type, $key, $life))
			{
				// ... and if client already has a previous version
				if(isset($_SERVER) && array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER))
				{
					$ifModifiedSince = DateTime::createFromFormat('D, d M Y H:i:s \G\M\T', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
					$lastModified = new DateTime();
					$lastModified->setTimestamp($this->getCreationTime($type, $key));
					
					// ... and if this previous version has not expired
					if($ifModifiedSince >= $lastModified)
					{
						// ... then we send a Not modified header
						http_response_code(304);
						
						if(isset($_SERVER) && array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER))
						{
							header('ETag: '.$_SERVER['HTTP_IF_NONE_MATCH']);
						}
						
						return true;
					}
				}
			}
			
			return false;
		}
		
		/**
		* Automatically issue a 304 response code when appropriated once a content has been generated.
		* If the client sent a ETag HTTP header and it matches the one of the generated content, then
		* send a 304 HTTP response code and return \c true.
		* In all cases this function will send an ETag response header.
		* 
		* \param string $data
		*   The data to generate the hash
		* 
		* \return bool
		*   \c true if a 304 response code has been sent, \c false otherwise
		*/
		static public function autoReplyIfNoneMatch(string $data): bool
		{
			if(http_response_code() !== 304)
			{
				$hash = md5($data);
				header('ETag: '.$hash);
				
				if(isset($_SERVER) && array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) && $_SERVER['HTTP_IF_NONE_MATCH'] === $hash)
				{
					http_response_code(304);
					return true;
				}
			}
			
			return false;
		}
		
		
		
		
		/**
		* Remove all data of a given cache type.
		* 
		* \return int
		*   Number of entries removed, -1 if error
		*
		* \see remove()
		*/
		abstract public function clear(string $type, string $glob=''): int;
		
		/**
		* Get if a data is stored in the cache and if it has not expired.
		* 
		* \return bool
		*   True if the data is found and not expired, false otherwise
		*
		* \see load()
		* \see save()
		*/
		abstract public function exists(string $type, string $key, int $life=self::LIFE_IMMORTAL): bool;
		
		/**
		* Move data in cache.
		* If data does not exists, do nothing.
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see remove()
		*/
		abstract public function move(string $fromType, string $fromKey, string $toType, string $toKey): bool;
		
		/**
		* Remove data from cache.
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see clear()
		* \see save()
		* \see load()
		* \see exists()
		*/
		abstract public function remove(string $type, string $key): bool;




		/**
		* Retrieve string from cache, decrypting them if needed.
		* This method calls the implementation specific loadInc() method.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param string $pwd
		*   Password to decrypt data, if required
		* 
		* \return string
		*   Data loaded
		*
		* \see exists()
		* \see loadInc()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Exception If password provided is incorrect or if data are encrypted and no password is provided
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		public function load(string $type, string $key, string $pwd=''): string
		{
			// Reset last date
			$this->lastDate = null;
			$this->lastDateType = $type;
			$this->lastDateKey = $key;
			
			// Retrieve the data
			$data = $this->loadInc($type, $key);
			
			$this->getMode($type);
			$this->typeModes[$type][6]++;
			
			// Extract mode and data
			$mode = mb_substr($data, 0, 5);
			
			// If data is encrypted, decrypt them
			if($mode === 'CRYPT')
			{
				if($pwd !== '')
				{
					$data = mb_substr($data, 5);
					
					Thrush_Crypt::decrypt($data, $pwd);
					
					if(mb_substr($data, 0, 16) !== $this->pwdCheck)
					{
						$data = '';
						throw new Thrush_Exception('Error', 'Password incorrect');
					}
					
					$data = mb_substr($data, 16);
				}
				else
				{
					throw new Thrush_Exception('Error', 'Data requested is crypted');
				}
			}
			else if($mode === 'CLEAR')
			{
				$data = mb_substr($data, 5);
			}
			
			return $data;
		}
		
		/**
		* Implementation specific method to retrieve some string from cache.
		* 
		* \return string
		*   Data loaded
		*
		* \see load()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		abstract protected function loadInc(string $type, string $key): string;
		
		
		
		
		/**
		* Retrieve data from cache.
		* This method calls the implementation specific loadMixedInc() method.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return mixed
		*   Data loaded
		*
		* \see exists()
		* \see loadInc()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Exception If password provided is incorrect or if data are encrypted and no password is provided
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		public function loadMixed(string $type, string $key): mixed
		{
			// Reset last date
			$this->lastDate = null;
			$this->lastDateType = $type;
			$this->lastDateKey = $key;
			
			// Retrieve the data
			$data = $this->loadMixedInc($type, $key);
			
			$this->getMode($type);
			$this->typeModes[$type][6]++;
			
			return $data;
		}
		
		/**
		* Implementation specific method to retrieve some data from cache.
		* 
		* \return mixed
		*   Data loaded
		*
		* \see load()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		abstract protected function loadMixedInc(string $type, string $key): mixed;
		
		
		
		
		/**
		* Store some string in cache, encrypting them if needed.
		* This method calls the implementation specific saveInc() method.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param string $data
		*   Data to store
		* \param string $pwd
		*   Password to encrypt data, if required
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see exists()
		* \see load()
		* \see remove()
		* \see saveInc()
		* \see saveMixed()
		*/
		public function save(string $type, string $key, string &$data, string $pwd=''): bool
		{
			$this->getMode($type);
			$this->typeModes[$type][5]++;
			
			if($pwd !== '')
			{
				$data2 = $this->pwdCheck.$data;
				Thrush_Crypt::encrypt($data2, $pwd);
				$data2 = 'CRYPT'.$data2;
			}
			else
			{
				$data2 = 'CLEAR'.$data;
			}
			
			return $this->saveInc($type, $key, $data2);
		}
		
		/**
		* Implementation specific method to store some string in cache.
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		*/
		abstract protected function saveInc(string $type, string $key, string &$data): bool;
		
		
		
		
		/**
		* Store some data in cache.
		* This method calls the implementation specific saveMixedInc() method.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param mixed $data
		*   Data to store
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		* \see saveMixedInc()
		*/
		public function saveMixed(string $type, string $key, &$data): bool
		{
			$this->getMode($type);
			$this->typeModes[$type][5]++;
			
			return $this->saveMixedInc($type, $key, $data);
		}
		
		/**
		* Implementation specific method to store some data in cache.
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see saveMixed()
		*/
		abstract protected function saveMixedInc(string $type, string $key, &$data): bool;
	}
	
	/**
	* This class implements a caching system using files
	*/
	class Thrush_Cache_Files extends Thrush_Cache
	{
		/**
		* string Path to cache directory from website root.
		*/
		protected ?string $root = null;
		
		/**
		* Constructor.
		* For performance and security reasons it is highly recommended for \c $root to be an absolute path (see \c realpath()).
		* 
		* \param string $websiteName
		*   Website name, used in HTTP User-Agent attribute
		* \param string $websiteURL
		*   Website URL, used in HTTP User-Agent attribute
		* \param string $root
		*   Path from current working directory to cache directory
		*/
		function __construct(string $websiteName, string $websiteURL, string $root)
		{
			parent::__construct($websiteName, $websiteURL);
			
			if(substr($root, -strlen(DIRECTORY_SEPARATOR)) !== DIRECTORY_SEPARATOR)
			{
				$root .= DIRECTORY_SEPARATOR;
			}
			
			if(!is_dir($root))
			{
				throw new Thrush_Exception('Error', 'Directory "'.$root.'" does not exist or is not a directory');
			}
			
			$this->root = $root;
		}
		
		/**
		* Get path from current working directory to cache directory, no matter if it exist or not.
		* 
		* \return string
		*   Path
		*
		* \see getFullPath()
		*/
		public function getRoot(): string
		{
			return $this->root;
		}
		
		/**
		* Get path from root of a data, no mater if it exists or not.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return string
		*   Path from root
		*
		* \see getRoot()
		*/
		public function getFullPath(string $type, string $key): string
		{
			if($type === '')
				return $this->root.$key;
			
			return $this->root.$type.DIRECTORY_SEPARATOR.$key;
		}
		
		/**
		* Get creation date of a stored data.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return int|null
		*   Unix timestamp or null if the data is not found in the cache
		*/
		public function getCreationTime(string $type, string $key): ?int
		{
			return filemtime($this->getFullPath($type, $key));
		}
		
		
		
		
		/**
		* Remove all data of a given cache type.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $glob
		*   Name pattern to remove, "" if all files are to be removed.
		* 
		* \return int
		*   Number of files removed, -1 if error
		*
		* \see remove()
		*/
		public function clear(string $type, string $glob=''): int
		{
			$nb = -1;
			$dir = $this->root.$type.DIRECTORY_SEPARATOR;
			
			if($glob !== '')
			{
				$arr = glob($dir.$glob);
				$nb = count($arr);
				
				foreach($arr as $file)
					unlink($file);
			}
			else
			{
				if ($handle = opendir($dir))
				{
					$nb = 0;
					while (false !== ($file = readdir($handle)))
					{
						if($file != '..' && $file != '.' && is_file($dir.$file) && $file != '.htaccess')
						{
							$nb++;
							unlink($dir.$file);
						}
					}
					
					closedir($handle);
				}
			}
			
			clearstatcache();
			
			return $nb;
		}
		
		/**
		* Get if a data is stored in the cache and if it has not expired.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param int $life
		*   Cache life in seconds
		* 
		* \return bool
		*   True if the data is found and not expired, false otherwise
		*
		* \see load()
		* \see save()
		*/
		public function exists(string $type, string $key, int $life=self::LIFE_IMMORTAL): bool
		{
			$fullPath = $this->getFullPath($type, $key);
			
			// If data exists in the cache
			if(file_exists($fullPath))
			{
				// Return whether it is still valid or not
				if($life <= 0)
				{
					return true;
				}
				else
				{
					return (($this->time-$this->getCreationTime($type, $key)) < $life);
				}
			}
			
			return false;
		}
		
		/**
		* Move data in cache.
		* If data does not exists, do nothing.
		* 
		* \param string $fromType
		*   Name of the cache where to retrieve data
		* \param string $fromKey
		*   Key associated to the data to retrieve
		* \param string $toType
		*   New cache name to store data
		* \param string $toKey
		*   New key to store data
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see remove()
		*/
		public function move(string $fromType, string $fromKey, string $toType, string $toKey): bool
		{
			$oldname = $this->getFullPath($fromType, $fromKey);
			$newname = $this->getFullPath($toType, $toKey);
			
			if(file_exists($oldname))
			{
				return rename($oldname, $newname);
			}
			
			return false;
		}
		
		/**
		* Remove data from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see clear()
		* \see save()
		* \see load()
		* \see exists()
		*/
		public function remove(string $type, string $key): bool
		{
			$fullPath = $this->getFullPath($type, $key);
			
			if(file_exists($fullPath))
				return unlink($fullPath);
			
			return true;
		}
		
		
		
		
		/**
		* Implementation specific method to retrieve some string from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return string
		*   Data loaded
		*
		* \see exists()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		protected function loadInc(string $type, string $key): string
		{
			$data = '';
			$fullpath = $this->getFullPath($type, $key);
			
			// Load from file only if exists
			if(file_exists($fullpath))
			{
				$data = file_get_contents($fullpath);
			}
			else
			{
				throw new Thrush_Cache_NoDataToLoadException();
			}
			
			return $data;
		}
		
		/**
		* Implementation specific method to retrieve some mixed from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return mixed
		*   Data loaded
		*
		* \see exists()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		protected function loadMixedInc(string $type, string $key): mixed
		{
			$data = '';
			$fullpath = $this->getFullPath($type, $key);
			
			// Load from file only if exists
			if(file_exists($fullpath))
			{
				$data = file_get_contents($fullpath);
			}
			else
			{
				throw new Thrush_Cache_NoDataToLoadException();
			}
			
			return unserialize($data);
		}
		
		
		
		
		/**
		* Implementation specific method to store some string in cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param string $data
		*   Data to store
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		*/
		protected function saveInc(string $type, string $key, string &$data): bool
		{
			$fullPath = $this->getFullPath($type, $key);
			
			return (file_put_contents($fullPath, $data)!==false);
		}
		
		/**
		* Implementation specific method to store some data in cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param mixed $data
		*   Data to store
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		*/
		protected function saveMixedInc(string $type, string $key, &$data): bool
		{
			$fullPath = $this->getFullPath($type, $key);
			
			return (file_put_contents($fullPath, serialize($data))!==false);
		}
	}
	
	/**
	* This class implements a caching system using volatile memory.
	* This means that the data will be lost once the instance is deleted.
	*/
	class Thrush_Cache_Volatile extends Thrush_Cache
	{
		/**
		* array Memory to store the cached data. The data are stored qccording to the following schema:
		* data[type][key] = array('t' => '...', 'd' => '...')
		*/
		protected array $data = array();
		
		/**
		* Constructor.
		*/
		function __construct()
		{
			parent::__construct('', '');
		}
		
		
		
		
		/**
		* Get creation date of a stored data.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return int|null
		*   Unix timestamp or null if the data is not found in the cache
		*/
		public function getCreationTime(string $type, string $key): ?int
		{
			if(array_key_exists($type, $this->data) && array_key_exists($key, $this->data[$type]))
				return $this->data[$type][$key]['t'];
			
			return null;
		}
		
		
		
		
		/**
		* Remove all data of a given cache type.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $glob
		*   Name pattern to remove (i.e. pattern for preg_match), "" if all entries are to be removed.
		* 
		* \return int
		*   Number of entries removed, -1 if error
		*
		* \see remove()
		*/
		public function clear(string $type, string $glob=''): int
		{
			$nb = -1;
			
			if(array_key_exists($type, $this->data))
			{
				if($glob !== '')
				{
					foreach($this->data[$type] as $key => &$value)
					{
						if(preg_match($glob, $key))
						{
							unset($this->data[$type][$key]);
							$nb++;
						}
					}
				}
				else
				{
					$nb = count($this->data[$type]);
					unset($this->data[$type]);
				}
			}
			
			return $nb;
		}
		
		/**
		* Get if a data is stored in the cache and if it has not expired.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param int $life
		*   Cache life in seconds
		* 
		* \return bool
		*   True if the data is found and not expired, false otherwise
		*
		* \see load()
		* \see save()
		*/
		public function exists(string $type, string $key, int $life=self::LIFE_IMMORTAL): bool
		{
			// If data exists in the cache
			if(array_key_exists($type, $this->data) && array_key_exists($key, $this->data[$type]))
			{
				// Return whether it is still valid or not
				if($life <= 0)
				{
					return true;
				}
				else
				{
					return (($this->time-$this->data[$type][$key]['time']) < $life);
				}
			}
			
			return false;
		}
		
		/**
		* Move data in cache.
		* If data does not exists, do nothing.
		* 
		* \param string $fromType
		*   Name of the cache where to retrieve data
		* \param string $fromKey
		*   Key associated to the data to retrieve
		* \param string $toType
		*   New cache name to store data
		* \param string $toKey
		*   New key to store data
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see remove()
		*/
		public function move(string $fromType, string $fromKey, string $toType, string $toKey): bool
		{
			if(array_key_exists($fromType, $this->data) && array_key_exists($fromKey, $this->data[$fromType]))
			{
				$this->data[$toType][$toKey] = $this->data[$fromType][$fromKey];
				
				return $this->remove($fromType, $fromKey);
			}
			
			return false;
		}
		
		/**
		* Remove data from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see clear()
		* \see save()
		* \see load()
		* \see exists()
		*/
		public function remove(string $type, string $key): bool
		{
			if(array_key_exists($type, $this->data) && array_key_exists($key, $this->data[$type]))
				unset($this->data[$type]);
			
			return true;
		}
		
		
		
		
		/**
		* Implementation specific method to retrieve some string from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return string
		*   Data loaded
		*
		* \see load()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		protected function loadInc(string $type, string $key): string
		{
			return $this->loadMixedInc($type, $key);
		}
		
		/**
		* Implementation specific method to retrieve some data from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return mixed
		*   Data loaded
		*
		* \see load()
		*
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		protected function loadMixedInc(string $type, string $key): mixed
		{
			$data = '';
			
			// Load from file only if exists
			if(array_key_exists($type, $this->data) && array_key_exists($key, $this->data[$type]))
			{
				$data = $this->data[$type][$key]['d'];
			}
			else
			{
				throw new Thrush_Cache_NoDataToLoadException();
			}
			
			return $data;
		}
		
		
		
		
		/**
		* Implementation specific method to store some string in cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param string $data
		*   Data to store
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		*/
		protected function saveInc(string $type, string $key, string &$data): bool
		{
			return $this->saveMixedInc($type, $key, $data);
		}
		
		/**
		* Implementation specific method to store some data in cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param mixed $data
		*   Data to store
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see save()
		*/
		protected function saveMixedInc(string $type, string $key, &$data): bool
		{
			if(!array_key_exists($type, $this->data))
				$this->data[$type] = array();
			
			$this->data[$type][$key] = array(
				't' => time(),
				'd' => $data
				);
			
			return true;
		}
	}
	
	class Thrush_Cache_NoDataToLoadException extends Thrush_Exception
	{
		function __construct()
		{
			parent::__construct('Error', 'Data not available in cache');
		}
	}
	
	class Thrush_Cache_TypeInvalidException extends Thrush_Exception
	{
		function __construct(string $typeName, string $className)
		{
			parent::__construct('Error', 'Type of data "'.$typeName.'" not supported by the type of cache "'.$className.'"');
		}
	}
?>