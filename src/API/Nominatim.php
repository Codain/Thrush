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
	class Thrush_Nominatim
	{
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		protected $cache = null;
		
		/**
		* string Email address to reach website owner in case Nominatim needs to do it
		*/
		protected $email = '';
		
		/**
		* Constructor.
		*
		* \param Thrush_Cache $cache
		*   Nominatim requires use of a Cache
		* \param string $email
		*   Email of someone to contact in case Nominatim need it
		*
		* \throws Thrush_Exception If no cache is provided
		* \throws Thrush_Exception If the email is not valid
		*/
		function __construct(Thrush_Cache $cache, string $email)
		{
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Nominatim server');
			}
			
			if(is_null($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
			{
				throw new Thrush_Exception('Error', 'An email is requested to query Nominatim server');
			}
			
			$this->cache = $cache;
			$this->email = $email;
		}
		
		/**
		* Query Nominatim server for the address of one or more OSM objects.
		* Execute a Nominatim Lookup command (see https://nominatim.org/release-docs/develop/api/Lookup/).
		*
		* \param array $keys
		*   Array of OSM objects to look for (a node is Nxxx, a Way is Wxxx and a 
		*   relation is Rxxx)
		* \param array $acceptLanguages
		*   List of languages to consider for the result (optional, default null). Either 
		*   use a standard RFC2616 accept-language string or a simple comma-
		*   separated list of language codes.
		*
		* \return array|null
		*   JSON array of the result (see https://nominatim.org/release-docs/develop/api/Lookup/) or null
		*/
		public function queryByObject(array $keys, array $acceptLanguages=null)
		{
			$endpointUrl = 'https://nominatim.openstreetmap.org/lookup';
			
			// If languages are specified, we filter to remove empty values
			if(is_array($acceptLanguages))
			{
				$acceptLanguages = array_filter($acceptLanguages);
			}
			
			// Generate URL-encoded query string
			$attributes = array(
				'email' => $this->email,
				'format' => 'json',
				'osm_ids' => implode(',', $keys )
				);
				
			if(!is_null($acceptLanguages))
			{
				$attributes['accept-language'] = implode(',', $acceptLanguages);
			}
			
			$queryString = http_build_query($attributes);
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('nominatim', $endpointUrl.'?'.$queryString, null, null, Thrush_Cache::LIFE_IMMORTAL);
				
				return json_decode($data, true);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
		
		/**
		* Query Nominatim server for the address of a coordinate.
		* Execute a Nominatim reverse command (see https://nominatim.org/release-docs/develop/api/Reverse/).
		*
		* \param float $lon
		*   Longitude of the coordinate
		* \param float $lat
		*   Latitude of the coordinate
		* \param array $acceptLanguages
		*   List of languages to consider for the result (optional, default null). Either 
		*   use a standard RFC2616 accept-language string or a simple comma-
		*   separated list of language codes.
		*
		* \return array|null
		*   JSON array of the result (see https://nominatim.org/release-docs/develop/api/Reverse/) or null
		*/
		public function queryByCoordinate(float $lon, float $lat, array $acceptLanguages=null)
		{
			$endpointUrl = 'https://nominatim.openstreetmap.org/reverse';
			
			// If languages are specified, we filter to remove empty values
			if(is_array($acceptLanguages))
			{
				$acceptLanguages = array_filter($acceptLanguages);
			}
			
			// Generate URL-encoded query string
			$attributes = array(
				'email' => $this->email,
				'format' => 'json',
				'lat' => $lat,
				'lon' => $lon
				);
				
			if(!is_null($acceptLanguages))
			{
				$attributes['accept-language'] = implode(',', $acceptLanguages);
			}
			
			$queryString = http_build_query($attributes);
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('nominatim', $endpointUrl.'?'.$queryString, null, null, Thrush_Cache::LIFE_IMMORTAL);
			
				return json_decode($data, true);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
	}
?>