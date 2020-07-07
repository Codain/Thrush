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
	require_once __DIR__.'/OpenStreetMap.php';
	require_once __DIR__.'/../Misc/OsmObjects.php';
	
	class Thrush_API_Overpass
	{
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		var $cache = null;
		var $osmApi = null;
		
		/**
		* Constructor.
		*
		* \param Thrush_Cache $cache
		*   Nominatim requires use of a Cache
		* \param Thrush_API_OpenStreetMap $osmApi
		*   OSM API Object
		*
		* \throws Thrush_Exception If no cache is provided
		*/
		function __construct(Thrush_Cache $cache, Thrush_API_OpenStreetMap $osmApi)
		{
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Overpass server');
			}
			
			$this->cache = $cache;
			
			$this->osmApi = $osmApi;
		}
		
		/**
		* Query Overpass server with a custom query and parse results, using cache according to \c $life parameter.
		*
		* \param string $query
		*   The query to send to Overpass
		* \param int $life
		*   See Thrush_Cache
		*
		* \return array
		*   Associative array containing date of data retrieval and an array of results
		*/
		public function query(string $query, int $life=Thrush_Cache::LIFE_IMMORTAL)
		{
			$endpointUrl = 'https://overpass-api.de/api/interpreter';

			try
			{
				$data = $this->cache->loadURLFromWebOrCache('osm', $endpointUrl.'?data='.urlencode( $query ), null, null, $life);
				$res = json_decode($data, true);
				
				$ret = array();
				
				foreach($res['elements'] as $e)
				{
					if($e['type'] === 'node')
					{
						$obj = new Thrush_API_OpenStreetMap_Node($e['id'], $res, $this->osmApi);
						$ret[] = $obj;
					}
					else if($e['type'] === 'way')
					{
						$obj = new Thrush_API_OpenStreetMap_Way($e['id'], $res, $this->osmApi);
						$ret[] = $obj;
					}
					else if($e['type'] === 'relation')
					{
						$obj = new Thrush_API_OpenStreetMap_Relation($e['id'], $res, $this->osmApi);
						$ret[] = $obj;
					}
				}
				
				$date = $this->cache->getLastDate();
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				$ret = null;
				$date = null;
			}
			
			return array('date' => $date, 'data' => $ret);
		}
		
		/**
		* Query Overpass server for objects with a key=value tag and parse results, using cache according to \c $life parameter.
		*
		* \param string $key
		*   The key of the tag to look for
		* \param string $value
		*   The value of the tag to look for
		* \param bool $minimumInformationNeccessary
		*   \c true to retrieve minimum information necessary for geometry, \c false otherwise
		* \param int $life
		*   See Thrush_Cache
		*
		* \return array
		*   Associative array containing date of data retrieval and an array of results
		*/
		public function queryObjectsWithKeyValue(string $key, string $value, bool $minimumInformationNeccessary, int $life=Thrush_Cache::LIFE_IMMORTAL)
		{
			$query = '[out:json][timeout:25];
				(
				  node["'.$key.'"="'.$value.'"];
				  way["'.$key.'"="'.$value.'"];
				  relation["'.$key.'"="'.$value.'"];
				);
				out body;';
				
			if($minimumInformationNeccessary)
			{
				$query .= '
				>;
				out skel qt;';
			}
			
			return $this->query($query, $life);
		}
	}
?>