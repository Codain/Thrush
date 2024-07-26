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
	
	class Thrush_Template
	{
		const FILE_FILENAME = 0;
		const FILE_CACHE_KEY = 1;
		const FILE_CACHE_DURATION = 2;
		
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object, if any
		*/
		protected $cache = null;
		
		/**
		* array Array of handles to be replaced by other handles
		*/
		protected $handleReplaceRules = array();

		/**
		* string Path to template directory
		*/
		protected $themesDir = "./";

		/**
		* array List of theme loaded
		*/
		protected $themesLoaded = array();
		
		/**
		* array Stack of theme activated, first one being current theme
		*/
		protected $themeStack = array();
		
		/**
		* array Set of handle data
		*/
		protected $handles = array();




		// augmented Backus–Naur form (ABNF):
		// <variable> = <variableName> / <string> / <constant> ["|" <function>]*
		// <function> = <functionName> "(" [<functionArgument> ","]* ")"
		// <functionName> = [a-zA-Z_]{1} [a-zA-Z0-9_]*
		// <functionArgument> = <variable>
		// <variableName> = 1*[<blockName> "."] <key>
		// <blockName> = [a-z0-9_]+
		// <condition> = <freeContent>
		// <key> = [A-Z0-9_]+
		// <string> = "\"" [.*?] "\""
		// <constant> = "true" / "TRUE" / "false" / "FALSE" / "null" / "NULL"
		// <freeContent> = [.*?] ; Free content
		
		// <begin> = "<!-- BEGIN " <blockName> *1[" ORDER " <key>] " -->"
		// <end> = "<!-- END " *1[<freeContent> " "] "-->"
		// <if> = "<!-- IF " <condition> " -->"
		// <isset> = "<!-- ISSET " <blockName> / <key> " -->"
		// <else> = "<!-- ELSE " *1[<freeContent> " "] "-->"
		// <endif> = "<!-- ENDIF " *1[<freeContent> " "] "-->"
		// <endisset> = "<!-- ENDISSET " *1[<freeContent> " "] "-->"
		
		// variable that holds all the data we'll be substituting into
		// the compiled templates.
		// ...
		// This will end up being a multi-dimensional array like this:
		// $this->templateData[block.][iteration#][child.][iteration#][child2.][iteration#][variablename] === value
		// if it's a root-level variable, it'll be like this:
		// $this->templateData[.][0][varname] === value
		protected $templateData = array();
		protected $templateDataPointer = array();
		protected $templateDataPointerI = 0;

		var $template_suffixe = '';
		
		/**
		* Constructor. Simply sets the root dir.
		*
		* \param string $root
		*   The root dir
		*
		* \throws Thrush_Exception If $root directory does not exists
		*/
		function __construct(string $root = "./")
		{
			// Make sure the path ends with a /
			if(mb_substr($root, -1) != '/')
			{
				$root .= '/';
			}
			
			// If the root directory does not exist, throw an Exception
			if(!is_dir($root))
			{
				throw new Thrush_Exception('Error', 'Root directory "'.$root.'" does not exists');
			}
			
			// Set root directory
			$this->themesDir = $root;
			
			$this->reset();
		}
		
		/**
		* Add a new handle replace rule. When the old handle will be called, 
		* the new handle will be used instead.
		*
		* \param string $old
		*   The old handle
		* \param string $new
		*   The new handle
		*/
		public function addHandleReplaceRule(string $old, string $new)
		{
			//echo $old.' redirige vers '.$new.'<br />';
			
			$this->handleReplaceRules[$old] = $new;
		}
		
		/**
		* Generate a key for cache use based on a filename or string.
		*
		* \param string $name
		*   The filename
		*
		* \return string
		*   The cache key
		*/
		protected function getCacheKey(string $name)
		{
			if(mb_substr($name, 0, 1) === '.')
				$name = mb_substr($name, 1);
			
			return str_replace('/', '$', $name);
		}
		
		/**
		* Reset all data, compiled templates and theme of this template object.
		*
		* \see resetData()
		*/
		public function reset()
		{
			$this->resetData();
			
			$this->handles = array();
			
			$this->setCurrentTheme('default');
		}
		
		/**
		* Reset all data of this template object.
		*
		* \see reset()
		*/
		protected function resetData()
		{
			$this->templateData = array();
			$this->templateData['.'] = Array();
			$this->templateData['.'][0] = Array();
			
			$this->templateDataPointerI = 0;
			$this->templateDataPointer[] = Array();
			$this->templateDataPointer[$this->templateDataPointerI] =& $this->templateData["."][0];
			
			$this->setRootVariables(array(
				'VOID' => ''
			));
		}
		
		/**
		* Associate to this template a cache and enable caching when applicable.
		*
		* \param Thrush_Cache $obj
		*   Cache object
		*/
		public function setCache(Thrush_Cache $obj)
		{
			$this->cache = $obj;
		}
		
		/**
		* This is an enhanced version of PHP explode function which takes care of brackets and quotes
		*
		* \param string $key
		*   The key to use to explode the string
		* \param string $str
		*   The string to explode
		* \param int $nb
		*   The number of chunks to return (-1 to return all chunks)
		*
		* \return array
		*   Array of string chunkss
		*/
		static function explodeConsideringPunctuation(string $key, string $str, int $nb=-1)
		{
			$ret = Array();
			
			if($nb === -1)
				$nb = PHP_INT_MAX-1;
			
			//if($key == ',')
			//	echo str_replace('<', '&lt;', mb_substr($str, 0, 50)).' selon '.$key.' avec nb='.$nb.'<br />';
			
			// On calcule la taille de la chaîne pour éviter de dépasser la fin
			$length = mb_strlen($str);
			
			// On calcul le nombre maximum d'itération pour éviter de parser la chaîne 
			// entre le dernier séparateur et la fin de la chaîne
			$nbMax = min(mb_substr_count($str, $key), $nb);
			
			while($length > 0 && $nbMax > 0)
			{
				$count = 0;
				$in = '';
				$pos = 0;
				
				// On positionne le curseur $pos devant la prochaine occurence du séparateur
				while($pos < $length)
				{
					$char = mb_substr($str, $pos, 1);
					
					if($char === '\\')
						$pos++;
					elseif($in === '')
					{
						if($char === $key && $count === 0)
							break;
						elseif($char === '(')
							$count++;
						elseif($char === ')')
							$count--;
						elseif($char === '"' || $char === "'")
							$in = $char;
					}
					elseif($in === $char)
						$in = '';
					
					$pos++;
				}
				
				// On empile la première partie jusqu'au séparateur détecté
				$ret[] = mb_substr($str, 0, $pos);
				
				// On retranche la partie retirée ainsi que le séparateur
				$str = mb_substr($str, $pos+1);
				$length -= $pos+1;
				$nbMax--;
			}
			
			if($length > 0)
				$ret[] = $str;
			
			//if($key == ',')
			//	foreach($ret as $r)
			//	{
			//		echo str_replace('<', '&lt;', mb_substr($r, 0, 50)).'<br />';
			//	}
				
			return $ret;
		}
		
		
		
		
		/**
		* Clear current theme stack and push a theme in the stack.
		* Throws Thrush_Exception if classe.php file does not exists in this 
		* theme.
		*
		* \param string $theme
		*   Theme name
		*
		* \throws Thrush_Exception If classe.php file does not exists for this theme
		*/
		public function setCurrentTheme(string $theme)
		{
			$this->themeStack = array();
			$this->pushCurrentTheme($theme);
		}
		
		/**
		* Push current theme in the stack.
		* Throws Thrush_Exception if classe.php file does not exists in this 
		* theme.
		*
		* \param string $theme
		*   Theme name
		*
		* \throws Thrush_Exception If classe.php file does not exists for this theme
		*/
		public function pushCurrentTheme(string $theme)
		{
			//echo 'Push '.$theme.'<br />';
			array_unshift($this->themeStack, $theme);
			
			if(!array_key_exists($theme, $this->themesLoaded))
			{
				$c = $this->themesDir.$theme.'/classe.php';
				if(file_exists($c))
				{
					require_once $c;
					
					$class = 'Theme'.ucfirst(str_replace('-', '', $theme));
					
					$this->themesLoaded[$theme] = new $class();
				}
				else
					throw new Thrush_Exception('Error', 'File "'.$c.'" cannot be found.');
			}
		}
		
		/**
		* Pop current theme from the stack, restoring previous one.
		*/
		public function popCurrentTheme()
		{
			//echo 'Pop<br />';
			array_shift($this->themeStack);
		}
		
		/**
		* Return current theme from the stack.
		*
		* \return string
		*   Current theme
		*
		* \throws Thrush_Exception If theme stack is empty (i.e. no theme defined)
		*/
		public function getCurrentTheme()
		{
			if(empty($this->themeStack))
			{
				throw new Thrush_Exception('Error', 'No theme as been defined');
			}
			
			return $this->themeStack[0];
		}
		
		
		
		
		/**
		* Add an array of variables to root.
		* Any existing variable with the same name will be overridden.
		*
		* \param array $keyValues
		*   Array of key-values
		*/
		public function setRootVariables(array $keyValues)
		{
			foreach($keyValues as $key => $values)
			{
				$this->assertKeyValidity($key);
			}
			
			$this->templateData['.'][0] = array_merge($this->templateData['.'][0], $keyValues);
		}

		/**
		* Add a single variable to root.
		* Any existing variable with the same name will be overridden.
		*
		* \param string $key
		*   Key of the variable
		* \param $value
		*   Value of the variable
		*/
		public function setRootVariable(string $key, $value)
		{
			$this->assertKeyValidity($key);
			
			$this->templateData['.'][0][$key] = $value;
		}
		
		/**
		* Assign an existing handle to a root variable.
		* Any existing variable with the same name will be overridden.
		* This can be used to effectively include a template in the middle of 
		* another template.
		* Note that all desired assignments to the variables in $handle should be done
		* BEFORE calling this function.
		*
		* \param string $key
		*   Key of the variable
		* \param string $handle
		*   The handle to associate
		 */
		public function setRootVariableHandle(string $key, string $handle)
		{
			$this->assertKeyValidity($key);
			
			// We evaluate it
			//if(estDeveloppeur())
			//	printLines($this->handles[$handle]['code']);
			eval($this->handles[$handle]['code']);
			
			// Save result if we are configured to do it
			if($this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY] != '' && !is_null($this->cache) && $this->cache->isEnabled('html') && !$this->cache->exists('html', $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY], $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_DURATION]))
			{
				$this->cache->save('html', $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY], $_str);
			}
			
			// assign the value of the generated variable to the given key.
			$this->setRootVariable($key, $_str);
		}
		
		
		
		
		
		/**
		* Check wether a handle matches allowed pattern.
		*
		* \param string $handle
		*   The handle to test
		*
		* \throws Thrush_Template_InvalidPatternException If handle is not allowed
		*/
		protected function assertBlockNameValidity(string $handle, string $context='')
		{
			$pattern = '/^[a-zA-Z0-9_]+$/';
			if(preg_match($pattern, $handle) === 0)
			{
				throw new Thrush_Template_InvalidPatternException($handle, $pattern, $context);
			}
		}
		
		/**
		* Check wether a key matches allowed pattern.
		*
		* \param string $key
		*   The key to test
		*
		* \throws Thrush_Template_InvalidPatternException If key is not allowed
		*/
		protected function assertKeyValidity(string $key, string $context='')
		{
			$pattern = '/^[A-Z0-9_]+$/';
			if(preg_match($pattern, $key) === 0)
			{
				throw new Thrush_Template_InvalidPatternException($key, $pattern, $context);
			}
		}
		
		/**
		* Check wether all items in an array are strings.
		*
		* \param array $array
		*   The Array of strings to test
		*
		* \return bool
		*   True if they are all valid, false otherwise
		*/
		protected static function isString(array &$array)
		{
			foreach($array as $str)
			{
				if(!(preg_match('/^"([^"]*)"$/i', $str) || preg_match("/^'([^']*)'$/i", $str) || is_numeric($str)))
					return false;
			}
			
			return true;
		}
		
		
		
		
		
		/**
		* Instanciate a new block with an array of variables.
		*
		* \param string $blockName
		*   Name of the block
		* \param array $keyValues
		*   Array of key-values
		*/
		public function setNewBlockVariables(string $blockName, array $keyValues)
		{
			$arr = &$this->getBlocksRefByName($blockName);
			
			$arr[] = $keyValues;
			
			unset($arr);
		}
		
		/**
		* Modify latest created block of a given name with an array of variables.
		* If no block has been set previously, a new one will be created.
		*
		* \param string $blockName
		*   Name of the block
		* \param array $keyValues
		*   Array of key-values
		*/
		public function setLastBlockVariables(string $blockName, array $keyValues)
		{
			$arr = &$this->getBlocksRefByName($blockName);
			$idx = count($arr)-1;
			
			if($idx >= 0)
			{
				$arr[$idx] = array_merge($arr[$idx], $keyValues);
			}
			else
			{
				$arr[] = $keyValues;
			}
			
			unset($arr);
		}
		
		/**
		* Get a copy of all variables of all created blocks with a given name.
		*
		* \param string $blockName
		*   Name of the block
		*
		* \return array
		*   Array of array of variables
		*/
		public function getBlocksByName(string $blockName)
		{
			return $this->getBlocksRefByName($blockName);
		}
		
		/**
		* Get a PHP reference to all variables of all created blocks with a given name.
		*
		* \param string $blockName
		*   Name of the block
		*
		* \return array
		*   Array of array of variables
		*/
		protected function &getBlocksRefByName(string $blockName)
		{
			$str = &$this->templateData;
			
			$blocks = explode('.', $blockName);
			$blockcount = count($blocks) - 1;
			
			for ($i = 0; $i < $blockcount; $i++)
			{
				$str = &$str[$blocks[$i].'.'];
				$str = &$str[count($str) - 1];
			}
			
			// Now we add the block that we're actually assigning to.
			// We're adding a new iteration to this block with the given
			// variable assignments.
			$str = &$str[$blocks[$blockcount].'.'];
			
			return $str;
		}
		
		/**
		* Get a PHP string to all variables of all created blocks with a given name.
		*
		* \param array $contexte
		*   Array of block names
		* \param string $key
		*   Key of the attribute, if any
		*
		* \return array
		*   Array of array of variables
		*/
		protected function getPhpStringForBlockKey(array &$contexte, string $key=null)
		{
			$ret = '$this->templateData';
			
			if($key !== '' && !is_null($key))
			{
				$this->assertKeyValidity($key);
			}
			
			if(!empty($contexte))
			{
				$countContexte = count($contexte);
				foreach($contexte as $i => $name)
				{
					$ret .= '["'.$name.'."]';
					
					if($i < $countContexte-1 || !is_null($key))
						$ret .= '[$_'.$name.'_i]';
				}
			}
			else
				$ret .= '["."][0]';
			
			if($key !== '' && !is_null($key))
				$ret .= '["'.$key.'"]';
			
			return $ret;
		}
		
		/**
		* Remove all blocks with a given name.
		*
		* \todo It seems an empty array lefts wether we would expect to have to more array.
		*
		* \param string $blockName
		*   Name of the block
		*/
		public function removeAllBlocksByName(string $blockName)
		{
			$str = &$this->getBlocksRefByName($blockName);
			
			$str = array();
		}
		
		
		
		
		
		/**
		* Generates a full path+filename for the given filename, which can either
		* be an absolute name, or a name relative to the rootdir for this Template
		* object.
		* 
		* \param string $filename
		*   The file to look for
		* \param string $mode
		*   Theme mode to consider
		*
		* \return string
		*   Full filepath from root
		*/
		protected function getFullFilename(string $filename, string $mode='')
		{
			$filenameFirst = mb_substr($filename, 0, 1);
			
			if($filenameFirst === '/' && is_file($filename))
			{
				return $filename;
			}
			else
			{
				$themes = $this->themeStack;
				
				if($mode != '')
				{
					foreach($this->themeStack as $theme)
						$themes[] = $theme.'~'.$mode;
				}
				
				foreach($themes as $theme)
				{
					$fullFilename = $this->themesDir.$theme.'/' . $filename.$this->template_suffixe;
					if(is_file($fullFilename))
					{
						return $fullFilename;
					}
				}
			}
			
			return '';
		}
		
		/**
		* Sets the template filenames for handles.
		* 
		* \param array $filenameArray
		*   Array of handles => File names
		* \param string $mode
		*   Theme mode to consider
		* \param string $cacheKey
		*   Use this Cache Key if needed
		* \param string $cacheDuration
		*   Use this Cache Duration if needed
		*
		* \see setFileForHandle()
		*
		* \throws Thrush_Exception If one file can't be found in any loaded themes
		*/
		public function setFilesForHandles(array $filenameArray, string $mode='', string $cacheKey='', int $cacheDuration=0)
		{
			reset($filenameArray);
			while(list($handle, $filename) = each($filenameArray))
			{
				$this->setFileForHandle($handle, $filename, $mode, $cacheKey, $cacheDuration);
			}
		}
		
		/**
		* Sets the template filename for a single handle.
		* 
		* \param string $handle
		*   Handle to set
		* \param string $filename
		*   Filename of the template
		* \param string $mode
		*   Theme mode to consider
		* \param string $cacheKey
		*   Use this Cache Key if needed
		* \param string $cacheDuration
		*   Use this Cache Duration if needed
		*
		* \see setFilesForHandles()
		*
		* \throws Thrush_Exception If the file can't be found in any loaded themes
		*/
		public function setFileForHandle(string $handle, string $filename, string $mode='', string $cacheKey='', int $cacheDuration=0)
		{
			// If a handle replace rule has been defined for this handle
			// We replace it
			if(array_key_exists($handle, $this->handleReplaceRules))
			{
				$handle = $this->handleReplaceRules[$handle];
			}
			
			// Construct full file path based on themes and mode
			$fullFilename = $this->getFullFilename($filename, $mode);
			if($fullFilename === '')
				throw new Thrush_Exception('Error', 'File "'.$filename.'" does not exists in any loaded themes.');
			
			// Associate the file with the handle and clearing previously built data
			// To do: Unset only if $fullfilename has changed
			$this->initializeHandle($handle);
			$this->handles[$handle]['filename'] = array($fullFilename, $cacheKey, $cacheDuration);
			
			$this->loadFileForHandle($handle);
		}
		
		
		
		
		
		protected function initializeHandle(string $handle)
		{
			$this->handles[$handle] = array(
				'filename' => null,
				'code' => null
				);
		}
		
		/**
		* Sets the content for a single handle. Content will be compiled.
		* 
		* \param string $handle
		*   Handle to set
		* \param string $content
		*   Content to set
		*/
		public function setContentForHandle(string $handle, string $content)
		{
			if(!array_key_exists($handle, $this->handles))
			{
				$this->initializeHandle($handle);
			}
			
			$this->handles[$handle]['code'] = $this->compile($content, '$_str');
		}
		
		
		
		
		
		/**
		* If not already done, load the file for the given handle, either from cache or file.
		* If the content had not been compiled, it will not compile it.
		*
		* \param string $handle
		*   Handle for which to load the file
		*
		* \throws Thrush_Exception
		*/
		protected function loadFileForHandle(string $handle)
		{
			if(!array_key_exists($handle, $this->handles))
			{
				throw new Thrush_Exception('Error', 'No handle "'.$handle.'" defined.');
			}
			
			if(is_null($this->handles[$handle]['filename']))
			{
				throw new Thrush_Exception('Error', 'No file set for handle "'.$handle.'".');
			}
			
			if(!is_null($this->handles[$handle]['code']))
			{
				// Do nothing
			}
			else
			{
				// Take from Cache if possible
				if($this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY] != '' && !is_null($this->cache) && $this->cache->isEnabled('html') && $this->cache->exists('html', $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY], $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_DURATION]))
				{
					$this->handles[$handle]['code'] = '$_str = \''.str_replace("'", "\'", $this->cache->load('html', $this->handles[$handle]['filename'][Thrush_Template::FILE_CACHE_KEY])).'\';';
				}
				else
				{
					$cle = $this->getCacheKey($this->handles[$handle]['filename'][Thrush_Template::FILE_FILENAME]);
					if(!is_null($this->cache) && $this->cache->isEnabled('template') && $this->cache->exists('template', $cle))
					{
						$this->handles[$handle]['code'] = $this->cache->load('template', $cle);
					}
					else
					{
						if(file_exists($this->handles[$handle]['filename'][Thrush_Template::FILE_FILENAME]))
						{
							$str = file_get_contents($this->handles[$handle]['filename'][Thrush_Template::FILE_FILENAME]);
						}
						else
							throw new Thrush_Exception('Error', 'File "'.$this->handles[$handle]['filename'][Thrush_Template::FILE_FILENAME].'" not found.');
						
						// Compile template and store it if enabled
						$this->handles[$handle]['code'] = $this->compile($str, '$_str');
						
						if(!is_null($this->cache) && $this->cache->isEnabled('template'))
						{
							$this->cache->save('template', $this->getCacheKey($this->handles[$handle]['filename'][Thrush_Template::FILE_FILENAME]), $this->handles[$handle]['code']);
						}
					}
				}
			}
		}
		
		
		
		
		
		
		/**
		* Parse a given template content, returning a set of PHP instructions able to include data in the template.
		*
		* \param string $content
		*   The content to compile
		* \param string $retvar
		*   If not empty, the returned code will be placed in this variable (dollar included), otherwise it will be echoed.
		*
		* \return string
		*   Compiled code
		*/
		protected function compile(string &$content, string $retvar='')
		{
			$offset = 0;
			$block_names = Array();
			$createdFunctions = array();
			$indentationLevel = 0;
			$indentation = '';
			
			$format = array('echo \'', '\';');
			$ret = $format[0];
			
			if($retvar !== '')
			{
				$format = array($retvar.' .= \'', '\';');
				$ret = $retvar.' = \'';
			}
			
			// On s'arrête au premier tag important
			$matches = array();
			while(preg_match('#(?:<!--)|(?:\\{(?!\s))#Ui', $content, $matches, PREG_OFFSET_CAPTURE, $offset))
			{
				// ATTENTION : $offset et $matches[x][1] sont en octets et non en caractères ! On ne doit donc pas les donner en argument de fonctions mb_*
				//var_dump($matches);
				
				// Ajout de tout le texte qui précède le code à interpréter
				$ret .= str_replace(array("\'", "'"), array("\\\'", "\'"), substr($content, $offset, $matches[0][1]-$offset));
				$type = $matches[0][0];
				$offset = $matches[0][1];
				
				// Transformation de la balise
				if($type === '<!--')
				{
					$contentToCompile = substr($content, $offset);
					
					if(preg_match('#^<!--\sBEGIN\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$param = $this->explodeConsideringPunctuation(' ', $matches[1][0]);
						$name = array_shift($param);
						
						$this->assertBlockNameValidity($name, $matches[0][0]);
						
						// Here we extract ordering and filtering attributes
						$orderBy = '';
						$orderOrder = '';
						$filterBy = '';
						while(!empty($param))
						{
							$p = array_shift($param);
							
							if($p === 'ORDER')
							{
								$orderBy = array_shift($param);
								$this->assertKeyValidity($orderBy, $matches[1][0]);
								
								if(empty($param))
								{
									$orderOrder = 'ASC';
								}
								elseif($param[0] === 'DSC' || $param[0] === 'DESC' || $param[0] === 'ASC')
								{
									$orderOrder = array_shift($param);
								}
								else
								{
									throw new Thrush_Exception('Error', 'Block ordering "'.$param[0].'" not recognized. ASC, DESC or nothing expected');
								}
							}
							elseif($p === 'FILTER')
							{
								$filterBy = array_shift($param);
							}
							else
							{
								throw new Thrush_Exception('Error', 'Block attribute "'.$p.'" not recognized. FILTER, ORDER or nothing expected');
							}
						}
						
						array_push($block_names, $name);
						$varref = $this->getPhpStringForBlockKey($block_names, null);
						
						$ret .= $format[1]."\n"
							."\n";
						
						// If we are requested to order the data, let's do it
						if($orderBy !== '')
						{
							$f = 'order_'.$orderBy.'_'.$orderOrder;
							
							if(!array_key_exists($f, $createdFunctions))
							{
								if($orderOrder === 'ASC')
								{
									$createdFunctions[$f] = 'function '.$f.'($a, $b) { if ($a["'.$orderBy.'"] == $b["'.$orderBy.'"]) { return 0; } return ($a["'.$orderBy.'"] < $b["'.$orderBy.'"]) ? -1 : 1; }'."\n"
										."\n";
								}
								elseif($orderOrder === 'DSC' || $orderOrder === 'DESC')
								{
									$createdFunctions[$f] = 'function '.$f.'($a, $b) { if ($a["'.$orderBy.'"] == $b["'.$orderBy.'"]) { return 0; } return ($a["'.$orderBy.'"] > $b["'.$orderBy.'"]) ? -1 : 1; }'."\n"
										."\n";
								}
							}
						}
						
						$ret .= $indentation.'if(isset('.$varref.'))'."\n"
							.$indentation.'{'."\n";
						
						$indentationLevel++;
						$indentation = str_repeat("\t", $indentationLevel);
						
						// If we are requested to order the data, we apply a callback
						if($orderBy !== '')
						{
							$ret .= $indentation.'usort('.$varref.', "order_'.$orderBy.'_'.$orderOrder.'");'."\n"
								."\n";
						}
						
						$ret .= $indentation.'$_'.$name.'_count = count('.$varref.');'."\n"
							.$indentation.'for($_'.$name.'_i = 0; $_'.$name.'_i < $_'.$name.'_count; ++$_'.$name.'_i)'."\n"
							.$indentation.'{'."\n";
						
						$indentationLevel++;
						$indentation = str_repeat("\t", $indentationLevel);
						
						if($filterBy !== '')
						{
							$ret .= $indentation.'if (!('.$this->compileCondition($filterBy, $block_names).')) continue;'."\n";
						}
						
						$ret .= $indentation.''.$this->getPhpStringForBlockKey($block_names, "_ROW").' = $_'.$name.'_i;'."\n"
							//.$indentation.'$this->templateDataPointerI++;'."\n"
							//.$indentation.'$this->templateDataPointer[$this->templateDataPointerI] =& '.$this->getPhpStringForBlockKey($block_names, '').';'."\n"
							.$indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sEND (.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						array_pop($block_names);
						
						$ret .= $format[1]."\n"
							//.$indentation.'$this->templateDataPointerI--;'."\n"
							;
							
						$indentationLevel--;
						$indentation = str_repeat("\t", $indentationLevel);
						
						$ret .= $indentation.'}'."\n";
						
						$indentationLevel--;
						$indentation = str_repeat("\t", $indentationLevel);
						
						$ret .= $indentation.'}'."\n"
							.$indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sISSET\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$name = $matches[1][0];
						
						if(strtoupper($name) === $name)
						{
							$this->assertKeyValidity($name, $matches[0][0]);
							
							$varref = $this->getPhpStringForBlockKey($block_names, '');
							
							$indentationLevel--;
							$indentation = str_repeat("\t", $indentationLevel);
							
							// Transformation de la balise
							$ret .= $format[1]."\n"
								."\n"
								.$indentation.'if(array_key_exists("'.$name.'", '.$varref.'))'."\n"
								.$indentation.'{'."\n";
							
							$indentationLevel++;
							$indentation = str_repeat("\t", $indentationLevel);
							
							$ret .= $indentation.$format[0];
						}
						else
						{
							$this->assertBlockNameValidity($name, $matches[0][0]);
							
							array_push($block_names, $name);
							$indentationLevel++;
							$indentation = str_repeat("\t", $indentationLevel);
							
							$varref = $this->getPhpStringForBlockKey($block_names, null);
							
							$indentationLevel--;
							$indentation = str_repeat("\t", $indentationLevel);
							
							// Transformation de la balise
							$ret .= $format[1]."\n"
								."\n"
								.$indentation.'if(isset('.$varref.'))'."\n"
								.$indentation.'{'."\n";
							
							$indentationLevel++;
							$indentation = str_repeat("\t", $indentationLevel);
							
							$ret .= $indentation.$format[0];
							
							array_pop($block_names);
						}
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sELSE\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$ret .= $format[1]."\n"
							.$indentation.'}'."\n"
							.$indentation.'else'."\n"
							.$indentation.'{'."\n"
							.$indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sENDISSET\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$ret .= $format[1]."\n"
							.$indentation.'}'."\n"
							.$indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sIF\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						list($name, $condition) = $this->explodeConsideringPunctuation(' ', $matches[1][0], 1);
						$name = $this->compileVariable($name, false);
						
						// Transformation de la balise
						$ret .= $format[1]."\n"
							."\n"
							.$indentation.'if('.$name.' '.$condition.')'."\n"
							.$indentation.'{'."\n";
						
						$indentationLevel++;
						$indentation = str_repeat("\t", $indentationLevel);
						
						$ret .= $indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sENDIF\s(.*)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$indentationLevel--;
						$indentation = str_repeat("\t", $indentationLevel);
						
						$ret .= $format[1]."\n"
							.$indentation.'}'."\n"
							.$indentation.$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					else
					{
						$ret .= '<!--';
						$offset += 4;
					}
				}
				elseif($type === '{')
				{
					$contentToCompile = substr($content, $offset+1);
					
					$var = $this->explodeConsideringPunctuation('}', $contentToCompile, 1);
					
					$code = $this->compileVariable($var[0], true);
					
					$ret .= '\'.'.$code.'.\'';
					
					$offset += strlen($var[0])+2;
				}
			}
			
			$ret .= str_replace(array("\'", "'"), array("\\\'", "\'"), substr($content, $offset));
			
			$ret .= $format[1];
			
			$ret = implode('', $createdFunctions).$ret;
			
			return $ret;
		}
		
		/**
		* Convert a function call in a template to a PHP callable instruction.
		* 
		* \param string $str
		*   String to parse
		* \param string $var
		*   Block to include in the function arguments ("VOID" to ignore)
		* 
		* \return string
		*   PHP instruction
		* 
		* \see compileVariable()
		*/
		protected function compileFunction(string $str, string $var='VOID')
		{
			static $atEnd = array('date');
			static $constFunctions = array(
				'htmlspecialchars', 
				'lcfirst', 
				'ltrim', 
				'md5', 
				'nl2br', 
				'rtrim', 
				'strip_tags', 
				'strlen', 
				'strpos', 
				'strrpos', 
				'strtolower',
				'strtoupper',  
				'str_replace',
				'substr',
				'trim',
				'ucfirst',
				'ucwords'
				);
			
			$pos = mb_strpos($str, '(');
			$rpos = mb_strrpos($str, ')');
			
			// Nom et des paramètres de la fonction
			$functionName = strtolower(mb_substr($str, 0, $pos));
			$functionParameters = mb_substr($str, $pos+1, $rpos-$pos-1);
			
			$varAdded = false;
			if($functionParameters != '')
			{
				$functionParameters = $this->explodeConsideringPunctuation(',', $functionParameters);
				$functionParameters = array_map("trim", $functionParameters);
				foreach($functionParameters as $cle => $parameter)
				{
					if($parameter === 'this')
					{
						$functionParameters[$cle] = $var;
						$varAdded = true;
					}
					else
						$functionParameters[$cle] = $this->compileVariable($parameter, true);
				}
			}
			else
				$functionParameters = Array();
			
			// Cas des fonctions sans variable
			if(!$varAdded && $var !== 'VOID')
			{
				// Cas des fonctions où la variable vient à la fin
				if(in_array($functionName, $atEnd))
				{
					array_push($functionParameters, $var);
				}
				// Sinon par défaut on met au début
				else
				{
					array_unshift($functionParameters, $var);
				}
			}
			
			if(array_key_exists($this->themeStack[0], $this->themesLoaded) && method_exists($this->themesLoaded[$this->themeStack[0]], $functionName))
				$str =  '$this->themesLoaded["'.$this->themeStack[0].'"]->'.$functionName.'('.implode(', ', $functionParameters).')';
			else
				$str =  $functionName.'('.implode(', ', $functionParameters).')';
			
			// TODO : cet eval pourrait être évité via cal_user_func & cie ?
			if(in_array($functionName, $constFunctions) && self::isString($functionParameters))
			{
				eval('$str = \'"\'.'.$str.'.\'"\';');
			}
			
			//printLines($str);
			return $str;
		}
		
		/**
		* Convert a condition in a template to a PHP callable instruction.
		* 
		* \param string $condition
		*   Condition to parse
		* \param array $block_names
		*   ??
		* 
		* \return string
		*   PHP instruction
		*/
		protected function compileCondition(string $condition, array $block_names)
		{
			return str_replace('$a[', $this->getPhpStringForBlockKey($block_names, '').'[', $condition);
		}
		
		/**
		* Convert a variable name or a function argument in a template to a PHP callable instruction.
		* 
		* \param string $variableName
		*   String to parse
		* 
		* \return string
		*   PHP instruction
		* 
		* \see compileVariable()
		*/
		protected function compileVariableNameOrFunctionArgument(string $variableName)
		{
			static $phpConstants = Array('true', 'TRUE', 'false', 'FALSE', 'null', 'NULL');
			$variable_first = mb_substr($variableName, 0, 1);
			
			// If it is a string, we don't change it
			if($variable_first === '"' || $variable_first === "'")
			{
			}
			// If it is a numeric value, we don't change it
			elseif(is_numeric($variableName))
			{
			}
			// If it is a constant, we don't change it
			elseif(in_array($variableName, $phpConstants))
			{
			}
			// If it is "VOID" we don't change it
			elseif($variableName === 'VOID')
			{
			}
			// If it's a variable, we convert it to a PHP variable
			elseif(preg_match('/^([a-z0-9_.]*)$/i', $variableName))
			{
				$contexte = explode('.', $variableName);
				$variableName = array_pop($contexte);
				
				$variableName = $this->getPhpStringForBlockKey($contexte, $variableName);
			}
			// Else, it's a PHP function, we compile it
			else
			{
				$variableName = $this->compileFunction($variableName);
			}
			
			return $variableName;
		}
		
		/**
		* Convert a variable in a template to a PHP callable instruction.
		* 
		* \param string $variable
		*   String to parse
		* \param bool $addIsset
		*   ??
		* 
		* \return string
		*   PHP instruction
		*/
		protected function compileVariable(string $variable, bool $addIsset=false)
		{
			$str = '';
			
			$blocs = $this->explodeConsideringPunctuation('|', $variable);
			$blocs = array_map("trim", $blocs);
			$variableBrut = array_shift($blocs);
			$variable = $this->compileVariableNameOrFunctionArgument($variableBrut);
			$str = $variable;
			
			if(!empty($blocs))
			{
				// Ici il va falloir encapsuler les blocs
				foreach($blocs as $i => $valeur)
				{
					$str = $this->compileFunction($valeur, $str);
					//if(estDeveloppeur()) printLines($str);
				}
			}
			
			if($addIsset && mb_substr($variable, 0, 1) === '$')
			{
				$vars = explode('.', $variableBrut);
				$cle = array_pop($vars);
				
				$str = '(array_key_exists("'.$cle.'", '.$this->getPhpStringForBlockKey($vars, '').')?'.$str.':"")';
			}
			
			//if(estDeveloppeur()) printLines($str);
			return $str;
		}
		
		
		
		
		
		
		/**
		* Load the file for the handle, compile the file,
		* and run the compiled code. This will either print out
		* the results of executing the template or return the result.
		*
		* \param string $handle
		*   The handle to render
		* \param bool $echo
		*   True to echo the result, False to return the result (default)
		*
		* \return string
		*   Result of rendering (if $echo set to true), empty string otherwise
		*
		* \throws Thrush_Exception
		*/
		public function render(string $handle, bool $echo=false)
		{
			if(!array_key_exists($handle, $this->handles))
			{
				throw new Thrush_Exception('Error', 'Handle "'.$handle.'" not defined');
			}
			
			if(is_null($this->handles[$handle]['code']))
			{
				throw new Thrush_Exception('Error', 'No code defined for handle "'.$handle.'"');
			}
			
			// Run the compiled code.
			//if(estDeveloppeur())
				//printLines($this->handles[$handle]['code']);
			eval($this->handles[$handle]['code']); 
			
			// If we were required to echo rather than returning result, let's do it
			if($echo)
			{
				echo $_str;
				$_str = '';
			}
			
			return $_str;
		}
	}
	
	class Thrush_Template_InvalidPatternException extends Thrush_Exception
	{
		function __construct(string $name, string $pattern, string $context='', string $file='', int $line=0)
		{
			$str = '"'.$name.'" shall match following pattern: '.$pattern;
			
			if($file !== '' && $line > 0)
				$str = $file.':'.$line.': '.$str;
			
			if($context !== '')
				$str .= ' in "'.str_replace('<', '&lt;', $context).'"';
			
			parent::__construct('Error', $str);
		}
	}
?>