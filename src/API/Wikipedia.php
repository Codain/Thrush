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
	 
	require_once __DIR__.'/../Cache.php';
	require_once __DIR__.'/../Exception.php';
	
	/**
	* Query a Nominatim server to get data
	*/
	class Thrush_Wikipedia
	{
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		protected $cache = null;
		
		/**
		* Constructor.
		*
		* \param Thrush_Cache $cache
		*   Nominatim requires use of a Cache
		*
		* \throws Thrush_Exception If no cache is provided
		* \throws Thrush_Exception If the email is not valid
		*/
		function __construct(Thrush_Cache $cache)
		{
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Wikipedia server');
			}
			
			$this->cache = $cache;
		}
		
		/**
		* Parse a Wikipedia article URL extracting its main properties.
		* For instance with "https://en.wikipedia.org/wiki/The_Beatles":
		*   - "scheme" = "https" (as in PHP parse_url() result)
		*   - "host" = "en.wikipedia.org" (as in PHP parse_url() result)
		*   - "language" = "en"
		*   - "title": "The_Beatles"
		*
		* \param string $url
		*   The URL to parse
		*
		* \return array
		*   Array of properties
		*/
		static function parseArticleUrl(string $url)
		{
			$data = parse_url($url);
			$ret = array();
			
			if(array_key_exists('scheme', $data))
				$ret['scheme'] = $data['scheme'];
			
			if(array_key_exists('host', $data))
			{
				$ret['host'] = $data['host'];
				
				$exp = explode('.', $data['host']);
				$ret['language'] = $exp[0];
			}
			
			$exp = explode('/', $data['path']);
			$ret['title'] = array_pop($exp);
			
			return $ret;
		}
		
		/**
		* Query Wikipedia server for page properties of an article.
		*
		* \param string $title
		*   Wikipedia article title
		* \param string $language
		*   Language to consider (use Wikipedia subdomain). Default is 'en' for English.
		* \param array $overrideAttributes
		*   List of attributes to customize the query, see https://en.wikipedia.org/w/api.php?action=help&modules=query%2Bpageprops
		*
		* \return array
		*   JSON array of the result
		*/
		public function queryPageProps(string $title, string $language='en', array $overrideAttributes=array())
		{
			$endpointUrl = 'https://'.$language.'.wikipedia.org/w/api.php';
			
			// Generate URL-encoded query string
			// See https://en.wikipedia.org/w/api.php?action=help&modules=query%2Bpageprops
			$attributes = array(
				'action' => 'query',
				'format' => 'json',
				'prop' => 'pageprops',
				'ppprop' => 'wikibase_item',
				'redirects' => '1',
				'titles' => $title
				);
			
			// Merge with user defined attributes
			$attributes = array_merge($attributes, $overrideAttributes);
			
			$queryString = http_build_query($attributes);
			
			// Fetch data
			try
			{
				$data = $this->cache->loadURLFromWebOrCache('wikipedia', $endpointUrl.'?'.$queryString, null, null, Thrush_Cache::LIFE_IMMORTAL);
				
				return json_decode($data, true);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
		
		/**
		* Query Wikipedia server for extracts of an article.
		*
		* \param string $title
		*   Wikipedia article title
		* \param string $language
		*   Language to consider (use Wikipedia subdomain). Default is 'en' for English.
		* \param array $overrideAttributes
		*   List of attributes to customize the query, see https://en.wikipedia.org/w/api.php?action=help&modules=query%2Bextracts
		*
		* \return array
		*   JSON array of the result
		*/
		public function queryExtracts(string $title, string $language='en', array $overrideAttributes=array())
		{
			$endpointUrl = 'https://'.$language.'.wikipedia.org/w/api.php';
			
			// Generate URL-encoded query string
			// See https://en.wikipedia.org/w/api.php?action=help&modules=query%2Bextracts
			$attributes = array(
				'action' => 'query',
				'format' => 'json',
				'prop' => 'extracts',
				'titles' => $title
				);
			
			// Merge with user defined attributes
			$attributes = array_merge($attributes, $overrideAttributes);
			
			$queryString = http_build_query($attributes);
			
			// Fetch data
			try
			{
				$data = $this->cache->loadURLFromWebOrCache('wikipedia', $endpointUrl.'?'.$queryString, null, null, Thrush_Cache::LIFE_IMMORTAL);
				
				return json_decode($data, true);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
	}
?>