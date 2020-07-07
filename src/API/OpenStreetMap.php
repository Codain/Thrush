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
	require_once __DIR__.'/../Misc/OsmObjects.php';
	require_once __DIR__.'/Nominatim.php';
	
	class Thrush_API_OpenStreetMap
	{
		protected $cache = null;
		protected $languages = array('en');
		
		function __construct(Thrush_Cache $cache, array $languages)
		{
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query OSM server');
			}
			
			$this->cache = $cache;
			
			if(!in_array('en', $languages))
				$languages[] = 'en';
			
			$this->languages = array();
			foreach($languages as $l)
			{
				if($l != '')
				{
					$this->languages[] = $l;
				}
			}
		}
		
		public function getLanguages()
		{
			return $this->languages;
		}
		
		/**
		* Query OpenStreetMap server for all data about a node.
		*
		* \param int $id
		*   Identifier of the node
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Node object or null
		*/
		public function queryNode($id)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = json_decode($this->cache->loadURLFromWebOrCache('osm', $endpointUrl.'node/'.$id.'.json', null, 'n'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL), true);
				
				if(!is_null($data))
				{
					return new Thrush_API_OpenStreetMap_Node($id, $data, $this);
				}
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				// Do nothing
			}
			
			return null;
		}
		
		/**
		* Query OpenStreetMap server for all data about a way.
		*
		* \param int $id
		*   Identifier of the way
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Way object or null
		*/
		public function queryWay($id)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = json_decode($this->cache->loadURLFromWebOrCache('osm', $endpointUrl.'way/'.$id.'/full.json', null, 'w'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL), true);
				
				if(!is_null($data))
				{
					return new Thrush_API_OpenStreetMap_Way($id, $data, $this);
				}
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				// Do nothing
			}
			
			return null;
		}
		
		/**
		* Query OpenStreetMap server for all data about a relation.
		*
		* \param int $id
		*   Identifier of the relation
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Relation object or null
		*/
		public function queryRelation($id)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = json_decode($this->cache->loadURLFromWebOrCache('osm', $endpointUrl.'relation/'.$id.'/full.json', null, 'r'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL), true);
				
				if(!is_null($data))
				{
					return new Thrush_API_OpenStreetMap_Relation($id, $data, $this);
				}
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				// Do nothing
			}
			
			return null;
		}
	}
?>