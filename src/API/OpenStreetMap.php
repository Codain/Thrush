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
	
	require_once __DIR__.'/../Exception.php';
	require_once __DIR__.'/../WebPageLoader.php';
	require_once __DIR__.'/../Misc/OsmObjects.php';
	require_once __DIR__.'/Nominatim.php';
	
	// To do:
	// callback: Would be better to not decode, encode then decode
	class Thrush_API_OpenStreetMap
	{
		/**
		* Flag used when querying a Way or Relation to remove from the data everything not linked to the geometry
		* (e.g. timestamp, version, changeset, user, uid...)
		*/
		const CALLBACK_KEEP_ONLY_GEOMETRY = 1;
		
		/**
		* Flag used when querying a Way or Relation to determine the center of the object
		*/
		const CALLBACK_COMPUTE_CENTER = 2;
		
		/**
		* Flag used when querying a Way or Relation to determine the bouding box of the object
		*/
		const CALLBACK_COMPUTE_BBOX = 4;
		
		/**
		* Thrush_WebPageLoader Pointer to a Thrush_WebPageLoader object
		*/
		protected $webPageLoader = null;
		
		/**
		*   array Array of ISO 639-1 preferred language codes
		*/
		protected $preferredLanguages = array();
		
		/**
		* Constructor.
		*
		* \param Thrush_WebPageLoader $webPageLoader
		*   Nominatim requires use of a WebPageLoader
		* \param array $preferredLanguages
		*   Array of ISO 639-1 language codes
		*
		* \throws Thrush_Exception If no WebPageLoader is provided
		*/
		function __construct(Thrush_WebPageLoader $webPageLoader, array $preferredLanguages)
		{
			if(is_null($webPageLoader))
			{
				throw new Thrush_Exception('Error', 'A WebPageLoader is mandatory to query OSM server');
			}
			
			$this->webPageLoader = $webPageLoader;
			
			// We define the preferred languages when querying for tags
			$this->preferredLanguages = array();
			foreach($preferredLanguages as $l)
			{
				if($l != '')
				{
					$this->preferredLanguages[] = $l;
				}
			}
		}
		
		/**
		* Get the preferred languages when querying for tags.
		*
		* \return array
		*   An array of ISO 639-1 preferred language codes
		*/
		public function getPreferredLanguages()
		{
			return $this->preferredLanguages;
		}
		
		/**
		* Callback to apply some preprocessing on the result for a way.
		*
		* \param string $data
		*   The data to preprocess
		* \param int $id
		*   Identifier of the way
		* \param int $flags
		*   Set of Callback flags to apply to the results
		*/
		public function queryWayCallback(string &$data, string $id, int $flags=0)
		{
			if($flags !== 0)
			{
				$tmpData = json_decode($data, true);
				
				$way = new Thrush_API_OpenStreetMap_Way($id, $tmpData, $this);
				foreach($tmpData['elements'] as &$e)
				{
					if($e['type'] === 'way' && $e['id'] == $id)
					{
						if($flags & self::CALLBACK_COMPUTE_CENTER)
						{
							$e['center'] = $way->getCenter();
						}
						
						if($flags & self::CALLBACK_COMPUTE_BBOX)
						{
							// We use OverPass format to store bounding box for compatibility purposes
							$bbox = $way->getBoundingBox();
							$e['bounds'] = array(
								'minlat' => $bbox[0][1],
								'minlon' => $bbox[0][0],
								'maxlat' => $bbox[1][1],
								'maxlon' => $bbox[1][0]
							);
						}
					}
					
					if($flags & self::CALLBACK_KEEP_ONLY_GEOMETRY)
					{
						unset($e['timestamp']);
						unset($e['version']);
						unset($e['changeset']);
						unset($e['user']);
						unset($e['uid']);
					}
				}
				
				$data = json_encode($tmpData);
			}
		}
		
		/**
		* Callback to apply some preprocessing on the result for a relation.
		*
		* \param string $data
		*   The data to preprocess
		* \param int $id
		*   Identifier of the relation
		* \param int $flags
		*   Set of Callback flags to apply to the results
		*/
		public function queryRelationCallback(string &$data, string $id, int $flags=0)
		{
			if($flags !== 0)
			{
				$tmpData = json_decode($data, true);
				
				$relation = new Thrush_API_OpenStreetMap_Relation($id, $tmpData, $this);
				foreach($tmpData['elements'] as &$e)
				{
					if($e['type'] === 'relation' && $e['id'] == $id)
					{
						if($flags & self::CALLBACK_COMPUTE_CENTER)
						{
							$e['center'] = $relation->getCenter();
						}
						
						if($flags & self::CALLBACK_COMPUTE_BBOX)
						{
							// We use OverPass format to store bounding box for compatibility purposes
							$bbox = $relation->getBoundingBox();
							$e['bounds'] = array(
								'minlat' => $bbox[0][1],
								'minlon' => $bbox[0][0],
								'maxlat' => $bbox[1][1],
								'maxlon' => $bbox[1][0]
							);
						}
					}
					
					if($flags & self::CALLBACK_KEEP_ONLY_GEOMETRY)
					{
						unset($e['timestamp']);
						unset($e['version']);
						unset($e['changeset']);
						unset($e['user']);
						unset($e['uid']);
					}
				}
				
				$data = json_encode($tmpData);
			}
		}
		
		/**
		* Query OpenStreetMap server for all data about a node.
		*
		* \param string $id
		*   Identifier of the node
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Node object or null
		*/
		public function queryNode(string $id)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = $this->webPageLoader->loadURLAsJSONAssociative($endpointUrl.'node/'.$id.'.json', null, 'n'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL, '', null, null);
				
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
		* \param string $id
		*   Identifier of the way
		* \param int $flags
		*   Set of Callback flags to apply to the results
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Way object or null
		*/
		public function queryWay(string $id, int $flags=0)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = $this->webPageLoader->loadURLAsJSONAssociative($endpointUrl.'way/'.$id.'/full.json', null, 'w'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'queryWayCallback'), $id, $flags), null);
				
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
		* \param string $id
		*   Identifier of the relation
		* \param int $flags
		*   Set of Callback flags to apply to the results
		*
		* \return Thrush_OpenStreetMap_Relation|null
		*   A Thrush_OpenStreetMap_Relation object or null
		*/
		public function queryRelation(string $id, int $flags=0)
		{
			$endpointUrl = 'https://api.openstreetmap.org/api/0.6/';
			
			try
			{
				$data = $this->webPageLoader->loadURLAsJSONAssociative($endpointUrl.'relation/'.$id.'/full.json', null, 'r'.$id.'.json', Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'queryRelationCallback'), $id, $flags), null);
				
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