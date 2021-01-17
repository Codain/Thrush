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
	
	// See https://www.sitemaps.org/protocol.html
	
	require_once __DIR__.'/Exception.php';
	
	class Thrush_SitemapUrlset
	{
		/**
		* DOMDocument DOMDocument object to store the Sitemap
		*/
		protected $domDocument = null;
		
		/**
		* DOMElement DOMElement object to store the 'urlset' root
		*/
		protected $urlset = null;
		
		/**
		* int Number of URL already stored in the Sitemap
		*/
		protected $nbUrls = 0;
		
		/**
		* Constructor.
		*/
		function __construct()
		{
			$this->domDocument = new DOMDocument('1.0', 'utf-8');
			$this->urlset = $this->domDocument->createElement('urlset');
			$this->urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
			$this->domDocument->appendChild($this->urlset);
		}
		
		/**
		* Escape a data value according to https://www.sitemaps.org/protocol.html#escaping
		*
		* \param string $data
		*   The data to escape
		*
		* \return string
		*   Escaped string
		*/
		protected function escape(string $data)
		{
			// https://www.sitemaps.org/protocol.html#escaping
			// Your Sitemap file must be UTF-8 encoded (you can generally do this when you save the file). As with all XML files, any data values (including URLs) must use entity escape codes for the characters listed in the table below.
			$data = str_replace(
				array('&', '\'', '"', '<', '>'), 
				array('&amp;', '&apos;', '&quot;', '&gt;', '&lt;'), 
				$data);
			
			return $data;
		}
		
		/**
		* Add a new url entry in the Sitemap.
		*
		* \param string $location
		*   The URL of the page. This URL must begin with the protocol (such as http) and end with a trailing slash, if your web server requires it. This value must be less than 2,048 characters.
		* \param array $optionalAttributes
		*   Set of optional attributes
		*
		* \throws Thrush_Exception If maximum number of URL reached (50000)
		* \throws Thrush_Exception If location does not start by a supported protocol
		* \throws Thrush_Exception If location length bigger than allowed (2048 bytes)
		*/
		public function addURL(string $location, array $optionalAttributes=array())
		{
			// Each Sitemap file that you provide must have no more than 50,000 URLs
			if($this->nbUrls === 50000)
			{
				throw new Thrush_Exception('Error', 'A Sitemap file must have no more than 50,000 URLs');
			}
			
			// This URL must begin with the protocol (such as http) [...]
			if(substr($location, 0, 7) !== "http://" && substr($location, 0, 8) !== "https://")
			{
				throw new Thrush_Exception('Error', 'Location must begin with the protocol (supported protocols: http, https)');
			}
			
			// This value must be less than 2,048 characters.
			if(strlen($location) >= 2048)
			{
				throw new Thrush_Exception('Error', 'Location must be less than 2,048 characters');
			}
			
			$object = $this->domDocument->createElement('url');
			
			$objectTmp = $this->domDocument->createElement('loc', $this->escape($location));
			$object->appendChild($objectTmp);
			
			foreach($optionalAttributes as $key => $value)
			{
				if(is_string($value))
					$objectTmp = $this->domDocument->createElement($key, $this->escape($value));
				else
					$objectTmp = $this->domDocument->createElement($key, $value);
				
				$object->appendChild($objectTmp);
			}
			
			// Append the URL in the Sitemap
			$this->urlset->appendChild($object);
			$this->nbUrls++;
        }
		
		/**
		* Get the list of Libxml errors and clear them.
		*
		* \return array
		*   The list of errors as formated strings.
		*/
		protected function getLibxmlErrors()
		{
			$ret = array();
			
			$errors = libxml_get_errors();
			foreach($errors as $error)
			{
				$out = '';
				
				switch($error->level)
				{
					case LIBXML_ERR_WARNING:
						$out .= 'Warning '.$error->code.': ';
						break;
					case LIBXML_ERR_ERROR:
						$out .= 'Error '.$error->code.': ';
						break;
					case LIBXML_ERR_FATAL:
						$out .= 'Fatal Error '.$error->code.': ';
						break;
				}
				
				$out .= trim($error->message);
				if ($error->file)
				{
					$out .= ' in '.$error->file;
				}
				
				$out .= ' on line '.$error->line;
				
				$ret[] = $out;
			}
			
			libxml_clear_errors();
			
			return $ret;
		}
		
		/**
		* Validate the current Sitemap and get list of potential errors.
		*
		* \return array
		*   The list of errors as formated strings.
		*/
		public function validate()
		{
			$ret = array();
			
			// LibXML limitation: Validation seems to work only on loaded XML, not on generated XML
			$xmlToValidate = new DOMDocument();
			$xmlToValidate->loadXML($this->domDocument->saveXML());
			
			$res = $xmlToValidate->schemaValidate('http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
			
			// Retrieve errors from libXML
			if($res === false)
			{
				$ret = $this->getLibxmlErrors();
			}
			
			return $ret;
		}
		
		/**
		* Get current Sitemap as XML.
		*
		* \return string
		*   The Sitemap as a string.
		*/
		public function getXML()
		{
			return $this->domDocument->saveXML();
		}
		
		/**
		* Save current Sitemap in a file.
		*
		* \throws Thrush_Exception If file size is bigger than allowed (50MB or 52,428,800 bytes)
		*/
		public function save(string $filepath)
		{
			$out = $this->domDocument->saveXML();
			
			// Each Sitemap file that you provide must be no larger than 50MB (52,428,800 bytes)
			if(strlen($out) > 52428800)
			{
				throw new Thrush_Exception('Error', 'A Sitemap file must be no larger than 50MB (52,428,800 bytes)');
			}
			
			file_put_contents($filepath, $out);
		}
	}
?>