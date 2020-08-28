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
		protected $themeDir = "./";

		/**
		* array List of theme loaded
		*/
		protected $themesLoaded = array();
		
		/**
		* array Stack of theme activated, first one being current theme
		*/
		protected $themeStack = array();
		
		/**
		* array Set of compiled content for each handle
		*/
		protected $compiledCode = array();

		/**
		* array Set of uncompiled content for each handle
		*/
		protected $uncompiledCode = array();




		// augmented Backus–Naur form (ABNF):
		// <variable> = <variableName> ["|" <function>]*
		// <function> = <functionName> "(" [<functionArgument> ","]* ")"
		// <functionArgument> = <variableName> / <argument>
		// <variableName> = 1*[<blockname> "."] <key>
		// <blockname> = [a-z0-9_]+
		// <condition> = <freeContent>
		// <key> = [A-Z0-9_]+
		// <freeContent> = [.*?] ; Free content
		
		// <begin> = "<!-- BEGIN " <blockname> *1[" ORDER " <key>] " -->"
		// <end> = "<!-- END " *1[<freeContent> " "] "-->"
		// <if> = "<!-- IF " <condition> " -->"
		// <isset> = "<!-- ISSET " <blockname> / <key> " -->"
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

		// Hash of filenames for each template handle.
		var $files = array();
		
		var $template_suffixe = '';
		
		var $block_nesting_level = 0;
		
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
			
			$this->compiled_code = array();
			$this->uncompiled_code = array();
			
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
			
			/*if($key == '}')
				echo '<hr />'.$str.' selon '.$key.' avec nb='.$nb;*/
			
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
			
			/*if($key == '|')
				var_dump($ret);*/
				
			return $ret;
		}
		
		
		
		
		/**
		* Set current theme for this template.
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
			$this->themeStack = array($theme);
			
			if(!array_key_exists($theme, $this->themesLoaded))
			{
				$c = $this->themesDir.$theme.'/classe.php';
				if(file_exists($c))
				{
					require_once $c;
					
					$class = 'Theme'.ucfirst($theme);
					
					$this->themesLoaded[$theme] = new $class();
				}
				else
					throw new Thrush_Exception('Error', 'File "'.$c.'" cannot be found.');
			}
		}
		
		/**
		* Push current theme in the stack for this template.
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
			
			$this->loadFileForHandle($handle);
			
			// Si le code n'a pas déjà été compilé : on le compile
			if(!isset($this->compiledCode[$handle]) || empty($this->compiledCode[$handle]))
			{
				// Compile it, with the "no echo statements" option on.
				$this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle], '$_str');
				unset($this->uncompiledCode[$handle]);
				if(!is_null($this->cache) && $this->cache->isEnabled('template'))
				{
					$this->cache->save('template', $this->getCacheKey($this->files[$handle][Thrush_Template::FILE_FILENAME]), $this->compiledCode[$handle]);
				}
			}
			
			// On l'éxecute
			//if(estDeveloppeur())
			//	printLines($this->compiledCode[$handle]);
			eval($this->compiledCode[$handle]);
			
			// Si on doit sauvegarder le résultat executé
			if($this->files[$handle][Thrush_Template::FILE_CACHE_KEY] != '' && !is_null($this->cache) && !$this->cache->exists('html', $this->files[$handle][Thrush_Template::FILE_CACHE_KEY], $this->files[$handle][Thrush_Template::FILE_CACHE_DURATION]))
			{
				$this->cache->save('html', $this->files[$handle][Thrush_Template::FILE_CACHE_KEY], $_str);
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
			$pattern = '/^[a-z0-9_]+$/';
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
			
			// Associate the file with the handle
			$this->files[$handle] = array($fullFilename, $cacheKey, $cacheDuration);
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
			$this->uncompiledCode[$handle] = $content;
			
			$this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle], '$_str');
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
			if(isset($this->uncompiledCode[$handle]) || isset($this->compiledCode[$handle]))
			{
				// Do nothing
			}
			else
			{
				if(!isset($this->files[$handle]))
				{
					throw new Thrush_Exception('Error', 'No file set for handle "'.$handle.'".');
				}

				// Take from Cache if possible
				if($this->files[$handle][Thrush_Template::FILE_CACHE_KEY] != '' && !is_null($this->cache) && $this->cache->isEnabled('html') && $this->cache->exists('html', $this->files[$handle][Thrush_Template::FILE_CACHE_KEY], $this->files[$handle][Thrush_Template::FILE_CACHE_DURATION]))
				{
					$this->compiledCode[$handle] = '$_str = \''.str_replace("'", "\'", $this->cache->load('html', $this->files[$handle][Thrush_Template::FILE_CACHE_KEY])).'\';';
				}
				else
				{
					$cle = $this->getCacheKey($this->files[$handle][Thrush_Template::FILE_FILENAME]);
					if(!is_null($this->cache) && $this->cache->isEnabled('template') && $this->cache->exists('template', $cle))
					{
						$this->compiledCode[$handle] = $this->cache->load('template', $cle);
					}
					else
					{
						if(file_exists($this->files[$handle][Thrush_Template::FILE_FILENAME]))
						{
							$str = file_get_contents($this->files[$handle][Thrush_Template::FILE_FILENAME]);
						}
						else
							throw new Thrush_Exception('Error', 'File "'.$this->files[$handle][Thrush_Template::FILE_FILENAME].'" not found.');
						
						$this->uncompiledCode[$handle] = $str;
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
			
			$format = array('echo \'', '\';');
			$ret = $format[0];
			
			if($retvar != '')
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
					
					if(preg_match('#^<!--\sBEGIN\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$param = $this->explodeConsideringPunctuation(' ', $matches[1][0]);
						$name = array_shift($param);
						
						$this->assertBlockNameValidity($name, $matches[0][0]);
						
						$orderBy = '';
						$orderOrder = '';
						$filterBy = '';
						while(!empty($param))
						{
							$p = array_shift($param);
							
							if($p === 'ORDER')
							{
								$orderBy = array_shift($param);
								
								if(!empty($param) && ($param[0] === 'DSC' || $param[0] === 'ASC'))
									$orderOrder = array_shift($param);
								else
									$orderOrder = 'ASC';
							}
							else if ($p === 'FILTER')
							{
								$filterBy = array_shift($param);
							}
						}
						
						array_push($block_names, $name);
						$varref = $this->getPhpStringForBlockKey($block_names, null);
						
						$ret .= $format[1]."\n"
							."\n";
						
						if($orderBy != '')
						{
							$f = 'order_'.$orderBy.'_'.$orderOrder;
							
							if($orderOrder == 'ASC' && !array_key_exists($f, $createdFunctions))
							{
								$createdFunctions[$f] = 'function '.$f.'($a, $b) { if ($a["'.$orderBy.'"] == $b["'.$orderBy.'"]) { return 0; } return ($a["'.$orderBy.'"] < $b["'.$orderBy.'"]) ? -1 : 1; }'."\n"
									."\n";
							}
							elseif($orderOrder == 'DSC' && !array_key_exists($f, $createdFunctions))
							{
								$createdFunctions[$f] = 'function '.$f.'($a, $b) { if ($a["'.$orderBy.'"] == $b["'.$orderBy.'"]) { return 0; } return ($a["'.$orderBy.'"] > $b["'.$orderBy.'"]) ? -1 : 1; }'."\n"
									."\n";
							}
						}
						
						if($filterBy != '')
						{
							$filterBy = 'if (!('.$this->compileCondition($filterBy, $block_names).')) continue;';
						}
						
						$ret .= str_repeat("\t", $this->block_nesting_level).'if(isset('.$varref.'))'."\n"
							.str_repeat("\t", $this->block_nesting_level).'{'."\n";
						
						if($orderBy != '')
						{
							$ret .= str_repeat("\t", $this->block_nesting_level+1).'usort('.$varref.', "order_'.$orderBy.'_'.$orderOrder.'");'."\n"
								."\n";
						}
						
						$ret .= str_repeat("\t", $this->block_nesting_level+1).'$_'.$name.'_count = count('.$varref.');'."\n"
							.str_repeat("\t", $this->block_nesting_level+1).'for($_'.$name.'_i = 0; $_'.$name.'_i < $_'.$name.'_count; ++$_'.$name.'_i)'."\n"
							.str_repeat("\t", $this->block_nesting_level+1).'{'."\n"
							.str_repeat("\t", $this->block_nesting_level+2).''.$filterBy.''."\n"
							.str_repeat("\t", $this->block_nesting_level+2).''.$this->getPhpStringForBlockKey($block_names, "_ROW").' = $_'.$name.'_i;'."\n"
							//.str_repeat("\t", $this->block_nesting_level+2).'$this->templateDataPointerI++;'."\n"
							//.str_repeat("\t", $this->block_nesting_level+2).'$this->templateDataPointer[$this->templateDataPointerI] =& '.$this->getPhpStringForBlockKey($block_names, '').';'."\n"
							.str_repeat("\t", $this->block_nesting_level+2).$format[0];
						
						$this->block_nesting_level += 2;
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sEND (.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						array_pop($block_names);
						$this->block_nesting_level -= 2;
						
						$ret .= $format[1]."\n"
							//.str_repeat("\t", $this->block_nesting_level+2).'$this->templateDataPointerI--;'."\n"
							.str_repeat("\t", $this->block_nesting_level+1).'}'."\n"
							.str_repeat("\t", $this->block_nesting_level).'}'."\n"
							.str_repeat("\t", $this->block_nesting_level).$format[0];
						
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sISSET\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$name = $matches[1][0];
						
						if(strtoupper($name) === $name)
						{
							$this->assertKeyValidity($name, $matches[0][0]);
							
							$varref = $this->getPhpStringForBlockKey($block_names, '');
							
							// Transformation de la balise
							$ret .= $format[1]."\n"
								."\n"
								.str_repeat("\t", $this->block_nesting_level-1).'if(array_key_exists("'.$name.'", '.$varref.'))'."\n"
								.str_repeat("\t", $this->block_nesting_level-1).'{'."\n";
							
							$ret .= str_repeat("\t", $this->block_nesting_level).$format[0];
						}
						else
						{
							$this->assertBlockNameValidity($name, $matches[0][0]);
							
							array_push($block_names, $name);
							$this->block_nesting_level++;
							$varref = $this->getPhpStringForBlockKey($block_names, null);
							
							// Transformation de la balise
							$ret .= $format[1]."\n"
								."\n"
								.str_repeat("\t", $this->block_nesting_level-1).'if(isset('.$varref.'))'."\n"
								.str_repeat("\t", $this->block_nesting_level-1).'{'."\n";
							
							$ret .= str_repeat("\t", $this->block_nesting_level).$format[0];
							
							array_pop($block_names);
							$this->block_nesting_level--;
						}
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sELSE\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$ret .= $format[1]."\n"
							.str_repeat("\t", $this->block_nesting_level).'}'."\n"
							.str_repeat("\t", $this->block_nesting_level).'else'."\n"
							.str_repeat("\t", $this->block_nesting_level).'{'."\n"
							.str_repeat("\t", $this->block_nesting_level).$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sENDISSET\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$ret .= $format[1]."\n"
							.str_repeat("\t", $this->block_nesting_level).'}'."\n"
							.str_repeat("\t", $this->block_nesting_level).$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sIF\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						list($name, $condition) = $this->explodeConsideringPunctuation(' ', $matches[1][0], 1);
						$name = $this->compileVariable($name, false);
						
						// Transformation de la balise
						$ret .= $format[1]."\n"
							."\n"
							.str_repeat("\t", $this->block_nesting_level).'if('.$name.' '.$condition.')'."\n"
							.str_repeat("\t", $this->block_nesting_level).'{'."\n";
						
						$this->block_nesting_level++;
						
						$ret .= str_repeat("\t", $this->block_nesting_level).$format[0];
						
						$offset += strlen($matches[0][0]);
					}
					elseif(preg_match('#^<!--\sENDIF\s(.*?)\s-->#Ui', $contentToCompile, $matches, PREG_OFFSET_CAPTURE))
					{
						//var_dump($matches);
						
						$this->block_nesting_level--;
						
						$ret .= $format[1]."\n"
							.str_repeat("\t", $this->block_nesting_level).'}'."\n"
							.str_repeat("\t", $this->block_nesting_level).$format[0];
						
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
				array_map("trim", $functionParameters);
				foreach($functionParameters as $cle => $parametre)
				{
					if($parametre === 'this')
					{
						$functionParameters[$cle] = $var;
						$varAdded = true;
					}
					else
						$functionParameters[$cle] = $this->compileVariable($parametre);
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
			$this->loadFileForHandle($handle);
			
			// If the code was not compiled: we compile it
			if(!isset($this->compiledCode[$handle]) || empty($this->compiledCode[$handle]))
			{
				$this->compiledCode[$handle] = $this->compile($this->uncompiledCode[$handle], '$_str');
				unset($this->uncompiledCode[$handle]);
				if(!is_null($this->cache) && $this->cache->isEnabled('template'))
					$this->cache->save('template', $this->getCacheKey($this->files[$handle][Thrush_Template::FILE_FILENAME]), $this->compiledCode[$handle]);
			}
			
			// Run the compiled code.
			//if(estDeveloppeur())
				//printLines($this->compiledCode[$handle]);
			eval($this->compiledCode[$handle]); 
			
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