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
	*/
	class Thrush_Cache
	{
		const LIFE_IMMORTAL = -1;
		const LIFE_DAY = 86400;
		const LIFE_WEEK = 604800;
		const LIFE_BIWEEK = 1296000;
		const LIFE_MONTH = 2635200;
		
		/**
		* string Path to cache directory from website root.
		*/
		protected $root = './cache/';
		
		/**
		* Array List of cache types to ignore.
		*/
		protected $ignoreList = array();
		
		/**
		* Bool Whether or not to ignore all cache types.
		*/
		protected $ignoreListAll = false;
		
		/**
		* string Website name (used for user-agent HTTP attribute).
		*/
		protected $websiteName = '';
		
		/**
		* string Website URL (used for user-agent HTTP attribute).
		*/
		protected $websiteURL = '';
		
		/**
		* string HTTP Context (for HTTP requests).
		*/
		protected $httpContext = null;
		
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
		
		protected $typeEnabled = array();
		
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
			$this->typeEnabled[$type] = true;
			
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
			$this->typeEnabled[$type] = false;
			
			return true;
		}
		
		/**
		* Get if a cache type is enabled or not.
		*
		* \todo Remove dependency to fonctions.php
		* 
		* \param string $type
		*   Name of the cache to use
		* 
		* \return bool
		*   True if enabled, false otherwise
		*
		* \see enable()
		* \see disable()
		*/
		public function isEnabled(string $type)
		{
			if(!array_key_exists($type, $this->typeEnabled))
				return false;
			
			return $this->typeEnabled[$type];
		}
		
		/**
		* Constructor.
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
			$this->ignoreListAll = false;
			$this->ignoreList = array();
			
			$this->root = $root;
			$this->websiteName = $websiteName;
			$this->websiteURL = $websiteURL;
			
			$options = array(
				'http' => array(
					'method' => "GET",
					'header' => "User-Agent: ".$this->websiteName." (".$this->websiteURL.")\r\n"
				)
			);
			
			$this->httpContext = stream_context_create($options);
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
			
			if($glob !== '')
			{
				$arr = glob($this->getDirectory($type).$glob);
				$nb = count($arr);
				
				foreach($arr as $file)
					unlink($file);
			}
			else
			{
				$dir = $this->getDirectory($type);
				
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
			// If it is required to ignore the whole cache or to ignore the given cache
			// We return \c false to make sure data will not be loaded. 
			if($this->ignoreListAll === true || in_array($type, $this->ignoreList))
				return false;
			
			// If data exists in the cache
			if(file_exists($this->getFullPath($type, $key)))
			{
				// Return whether it is still valid or not
				if($life <= 0)
					return true;
				else
					return ((time()-$this->getCreationTime($type, $key)) < $life);
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
		* Get path to a cache type, no matter if it exist or not.
		* 
		* \param string $type
		*   Name of the cache to use
		* 
		* \return string
		*   Path from root
		*
		* \see getRoot()
		* \see getFullPath()
		*/
		public function getDirectory(string $type)
		{
			if($type !== '')
				return $this->root.$type.'/';
			else
				return $this->root;
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
		* \see getDirectory()
		*/
		public function getFullPath(string $type, string $key)
		{
			return $this->getDirectory($type).$key;
		}
		
		/**
		* Get path from current working directory to cache directory, no matter if it exist or not.
		* 
		* \return string
		*   Path
		*
		* \see getDirectory()
		*/
		public function getRoot()
		{
			return $this->root;
		}
		
		/**
		* Consider all caches by its name.
		*
		* \see ignoreAll()
		*/
		public function considerAll()
		{
			$this->ignoreListAll = false;
		}
		
		/**
		* Consider a cache by its name.
		* 
		* \param string $type
		*   Name of the cache to ignore
		*
		* \see ignoreType()
		*/
		public function considerType(string $type)
		{
			if(is_array($this->ignoreList))
			{
				foreach (array_keys($this->ignoreList, $type, true) as $key)
				{
					unset($this->ignoreList[$key]);
				}
			}
		}
		
		/**
		* Ignore a cache by its name.
		* 
		* \param string|Array $type
		*   Name of the cache to ignore or set of names
		*
		* \see considerType()
		*/
		public function ignoreType(string $type)
		{
			if(is_array($this->ignoreList) && !in_array($type, $this->ignoreList))
			{
				if(is_array($type))
				{
					$this->ignoreList = array_merge($this->ignoreList, $type);
				}
				else
				{
					$this->ignoreList[] = $type;
				}
				
				$this->ignoreList = array_unique($this->ignoreList, SORT_STRING);
			}
		}
		
		/**
		* Ignore all caches.
		*
		* \see considerAll()
		*/
		public function ignoreAll()
		{
			$this->ignoreListAll = true;
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
		* \return mixed
		*   Data loaded
		*
		* \see chargerUrl()
		* \see exists()
		* \see save()
		* \see remove()
		*
		* \throws Thrush_Exception If password provided is incorrect or if data are encrypted and no password is provided
		*/
		public function load(string $type, string $key, string $pwd='')
		{
			$data = file_get_contents($this->getFullPath($type, $key));
			
			// Reset last date
			$this->lastDate = null;
			$this->lastDateType = $type;
			$this->lastDateKey = $key;
			
			// Extract mode and data
			$mode = mb_substr($data, 0, 5);
			
			// If data is encrypted, decrypt them
			if($mode === 'CRYPT')
			{
				if($pwd !== '')
				{
					$data = mb_substr($data, 5);
					
					Thrush_Crypt::decrypt($data, $pwd);
					
					if(substr($data, 0, 16) !== $this->pwdCheck)
					{
						$data = '';
						throw new Thrush_Exception('Error', 'Password incorrect');
					}
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
		* Fetch data from a given URL, either from Web or cache.
		* If the data is already in cache and cache has not expired, load data from cache.
		* If this cache type is disabled, \c life attribute is ignored.
		* 
		* \param string $type
		*   Name of the cache to use
		* \param string $url
		*   URL of the data to load
		* \param int $life
		*   Cache life in seconds
		* \param string $pwd
		*   Password to encrypt data, if required
		* 
		* \return string
		*   Data
		*
		* \see exists()
		* \see load()
		*/
		public function loadURLFromWebOrCache(string $type, string $url, $life=self::LIFE_IMMORTAL, string $pwd='')
		{
			$key = md5($url);
			
			if(!$this->isEnabled($type) || !$this->exists($type, $key, $life))
			{
				//echo '<span style="color: red;">CACHE: Loading type '.$type.' with URL '.$url.'</span><br />';
				
				$data = @file_get_contents($url, false, $this->httpContext);
				$response = Thrush_HTTPException::parseHeaders($http_response_header);
				
				if($response['response_code'] === 200)
				{
					$this->save($type, $key, $data, $pwd);
					
					// Save last retrieved data
					$this->lastDate = new DateTime();
					$this->lastDateType = $type;
					$this->lastDateKey = $key;
					
					return $data;
				}
				else
				{
					throw new Thrush_HTTPException($url, $response);
				}
			}
			
			return $this->load($type, $key, $pwd);
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
			if(file_exists($this->getDirectory($type).$key))
				return unlink($this->getDirectory($type).$key);
			
			return true;
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
			if($pwd !== '')
			{
				$data2 = $this->pwdCheck.$data;
				Thrush_Crypt::encrypt($data2, $pwd);
				
				return file_put_contents($this->getDirectory($type).$key, 'CRYPT'.$data2);
			}
			else
			{
				return file_put_contents($this->getDirectory($type).$key, 'CLEAR'.$data);
			}
		}
	}
?>