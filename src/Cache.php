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
	* This class allows to access to a cache in order to store/retrieve data.
	* Cache is implemented as a system file.
	*
	* Regarding external URL call, each type of cache can be configured with a defined mode:
	*  - DefaultMode: If the requested data are already stored and are not outdated, it will be readback. Otherwise an external call will be made.
	*  - CacheOnlyMode: If the requested data are already stored no matter wether it is outdated, it will be readback. Otherwise an Exception will be thrown.
	*  - DefaultWithLimitsMode: Same as default mode but with a counter (semaphore) before switching to CacheOnlyMode.
	*  - AlwaysRefreshMode: Always issue external call and save in cache.
	*/
	class Thrush_Cache
	{
		const LIFE_IMMORTAL = -1;
		const LIFE_HOUR = 3600;
		const LIFE_DAY = 86400;
		const LIFE_WEEK = 604800;
		const LIFE_BIWEEK = 1296000;
		const LIFE_MONTH = 2635200;
		
		const DefaultMode = 0;
		const CacheOnlyMode = 1;
		const DefaultWithLimitsMode = 2;
		const AlwaysRefreshMode = 3;
		
		/**
		* string Path to cache directory from website root.
		*/
		protected $root = './cache/';
		
		/**
		* string Website name (used for user-agent HTTP attribute).
		*/
		protected $websiteName = '';
		
		/**
		* string Website URL (used for user-agent HTTP attribute).
		*/
		protected $websiteURL = '';
		
		/**
		* DateTime Date of the last retrieved data.
		*/
		protected $lastDate = null;
		
		/**
		* string Type of the last data retrieved.
		*/
		protected $lastDateType = null;
		
		/**
		* string Key of the last data retrieved.
		*/
		protected $lastDateKey = null;
		
		/**
		* string Hash to check password. Must be 16 characters.
		*/
		protected $pwdCheck = '!7+t6]3FVf7;xK#X';
		
		/**
		* array Set of modes and semaphore for each type.
		*/
		protected $typeModes = array();
		
		/**
		* int Default mode.
		*/
		protected $defaultMode = self::DefaultMode;
		
		/**
		* int Default number of tokens when in DefaultWithLimitsMode.
		*/
		protected $defaultSemaphore = 10;
		
		/**
		* bool Default value if a cache is neither enabled nor disabled.
		*/
		protected $defaultEnabled = false;
		
		/**
		* int Timestamp to evaluate item life
		*/
		protected $time = null;
		
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
				0, 
				$this->defaultEnabled,
				null,
				0, 	// Number of save operations
				0 	// Number of load operations
				);
		}
		
		/**
		* Set mode for a given type of cache.
		*
		* \param int $mode
		*   Mode to assign
		* \param int $semaphore
		*   Number of tokens when in DefaultWithLimitsMode (optional, -1 to use default value or keep existing value)
		* \param string $type
		*   Name of the cache to use (optional)
		*
		* \see getMode
		*/
		public function setMode(int $mode, int $semaphore=-1, string $type='')
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
		* \return int
		*
		* \see setMode
		*/
		protected function getMode(string $type)
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
		protected function getSemaphore(string $type)
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
		public function getStatistics()
		{
			$ret = array();
			
			foreach($this->typeModes as $key => $values)
			{
				$ret[] = array(
					'type' => $key,
					'mode' => $values[0],
					'tokensLeft' => max($values[1], 0),
					'tokensMissing' => ($values[0]===self::DefaultWithLimitsMode?max(-$values[1], 0):'0'),
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
		public function enable(string $type)
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
		public function disable(string $type)
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
		public function isEnabled(string $type)
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
		* Constructor.
		* For performance reasons it is highly recommended for \c $root to be an absolute path (see \c realpath()).
		* 
		* \param string $websiteName
		*   Website name, used in HTTP User-Agent attribute
		* \param string $websiteURL
		*   Website URL, used in HTTP User-Agent attribute
		* \param string $root
		*   Path from current working directory to cache directory
		*/
		function __construct(string $websiteName, string $websiteURL, string $root='./cache/')
		{
			if(!file_exists($root) || !is_dir($root))
			{
				throw new Thrush_Exception('Error', 'Directory "'.$root.'" does not exist or is not a directory');
			}
			
			$this->root = $root;
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
		public function clear(string $type, string $glob='')
		{
			$nb = -1;
			$dir = $this->root.$type.'/';
			
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
		* get if a data is stored in the cache and if it has not expired.
		* If variable \c $_GET['recalcul'] is set, this function returns always \c false.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param int $life
		*   Cache life in seconds
		* 
		* \return bool
		*   True if there is no error, false otherwise
		*
		* \see load()
		* \see save()
		*/
		public function exists(string $type, string $key, $life=self::LIFE_IMMORTAL)
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
		* Get creation date of last loaded data.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* 
		* \return int
		*   Unix timestamp
		*/
		public function getCreationTime(string $type, string $key)
		{
			return filemtime($this->getFullPath($type, $key));
		}
		
		/**
		* Get creation date of last data.
		* 
		* \return DateTime
		*   PHP DateTime
		*/
		public function getLastDate()
		{
			if(is_null($this->lastDate) && !is_null($this->lastDateType) && !is_null($this->lastDateKey))
			{
				$this->lastDate = new DateTime();
				$this->lastDate->setTimestamp($this->getCreationTime($this->lastDateType, $this->lastDateKey));
			}
			
			return $this->lastDate;
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
		public function getFullPath(string $type, string $key)
		{
			if($type === '')
				return $this->root.$key;
			
			return $this->root.$type.'/'.$key;
		}
		
		/**
		* Get path from current working directory to cache directory, no matter if it exist or not.
		* 
		* \return string
		*   Path
		*
		* \see getFullPath()
		*/
		public function getRoot()
		{
			return $this->root;
		}
		
		/**
		* Retrieve data from cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param string $pwd
		*   Password to encrypt data, if required
		* 
		* \return string
		*   Data loaded
		*
		* \see chargerUrl()
		* \see exists()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Exception If password provided is incorrect or if data are encrypted and no password is provided
		* \throws Thrush_Cache_NoDataToLoadException If data requested not in cache
		*/
		public function load(string $type, string $key, string $pwd='')
		{
			// Reset last date
			$this->lastDate = null;
			$this->lastDateType = $type;
			$this->lastDateKey = $key;
			
			$data = '';
			$fullpath = $this->getFullPath($type, $key);
			
			// Load from file only if exists
			if(file_exists($fullpath))
			{
				$this->getMode($type);
				$this->typeModes[$type][6]++;
				
				$data = file_get_contents($fullpath);
				
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
			}
			else
			{
				throw new Thrush_Cache_NoDataToLoadException();
			}
			
			return $data;
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
		public function loadURLFromWebOrCache(string $type, string $url, array $postData=null, string $key=null, $life=self::LIFE_IMMORTAL, string $pwd='', array $callback=null)
		{
			if(is_null($key))
			{
				$key = md5($url.(is_null($postData)?'':serialize($postData)));
			}
			
			//echo '<span style="color: red;">CACHE: Loading type '.$type.' with URL '.$url.' (key '.$key.') and life '.$life.'</span><br />';
			
			// Determine wether we willl need to launch query or to return cache
			$launchQuery = false;
			$mode = $this->getMode($type);
			
			$loop = true;
			while($loop)
			{
				$loop = false;
				switch($mode)
				{
					case self::AlwaysRefreshMode:
						$launchQuery = true;
						break;
					case self::DefaultWithLimitsMode:
						if($this->exists($type, $key, $life))
						{
							$mode = self::CacheOnlyMode;
						}
						else
						{
							if($this->getSemaphore($type))
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
					case self::CacheOnlyMode:
						if(!$this->exists($type, $key, self::LIFE_IMMORTAL))
						{
							throw new Thrush_Cache_NoDataToLoadException();
						}
						break;
					case self::DefaultMode:
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
						$this->save($type, $key, $data, $pwd);
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
		public function autoReplyIfNotModified(string $type, string $key, $life=self::LIFE_IMMORTAL)
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
		static public function autoReplyIfNoneMatch(string $data)
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
		public function remove(string $type, string $key)
		{
			$fullPath = $this->getFullPath($type, $key);
			
			if(file_exists($fullPath))
				return unlink($fullPath);
			
			return true;
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
		*/
		public function move(string $fromType, string $fromKey, string $toType, string $toKey)
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
		* Store some data in cache.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $key
		*   Key associated to the data
		* \param mixed $data
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
		*/
		public function save(string $type, string $key, string &$data, string $pwd='')
		{
			$fullPath = $this->getFullPath($type, $key);
			
			$this->getMode($type);
			$this->typeModes[$type][5]++;
			
			if($pwd !== '')
			{
				$data2 = $this->pwdCheck.$data;
				Thrush_Crypt::encrypt($data2, $pwd);
				
				return file_put_contents($fullPath, 'CRYPT'.$data2);
			}
			else
			{
				return file_put_contents($fullPath, 'CLEAR'.$data);
			}
		}
	}
	
	class Thrush_Cache_NoDataToLoadException extends Thrush_Exception
	{
		function __construct()
		{
			parent::__construct('Error', 'Data not available in cache');
		}
	}
?>