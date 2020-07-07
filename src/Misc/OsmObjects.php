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
	
	abstract class Thrush_OpenStreetMap_Object
	{
		var $id;
		var $type;
		var $data = null;
		protected $tags = array();
		var $center = null; // array($longitude, $latitude)
		var $bbox = null; // array(array($min_longitude, $min_latitude), array($max_longitude, $max_latitude))
		var $border = null;
		protected $base = null;
		
		function __construct($type, $id, Thrush_API_OpenStreetMap $base)
		{
			$this->id = $id;
			$this->type = $type;
			$this->base = $base;
		}
		
		public function getUid()
		{
			$t = '';
			if($this->type === 'relation')
			{
				$t = 'R';
			}
			else if($this->type === 'way')
			{
				$t = 'W';
			}
			else if($this->type === 'node')
			{
				$t = 'N';
			}
			
			return $t.$this->id;
		}
		
		public function getId()
		{
			return $this->id;
		}
		
		public function getType()
		{
			return $this->type;
		}
		
		function setTag(string $key, string $value)
		{
			$this->tags[$key] = $value;
		}
		
		function setTags(array $arr)
		{
			$this->tags = array_merge($this->tags, $arr);
		}
		
		function resetTags(array $arr)
		{
			$this->tags = $arr;
		}
		
		function getTag(string $key, bool $preferredLanguage = false)
		{
			// First we try to find the tag in preferred language if required
			if($preferredLanguage)
			{
				foreach($this->base->getLanguages() as $l)
				{
					if(array_key_exists($key.':'.$l, $this->tags))
					{
						return array($key.':'.$l, $this->tags[$key.':'.$l], $l);
					}
				}
			}
			
			// If not available in prefered language, we try to find default one
			if(array_key_exists($key, $this->tags))
			{
				return array($key, $this->tags[$key], '');
			}
			
			return null;
		}
		
		function hasTag(string $key)
		{
			return array_key_exists($key, $this->tags);
		}
		
		function getLink($site='osm.org')
		{
			if($site == 'JOSM')
			{
				switch($this->type)
				{
					case 'node':
						$osmt = 'n';
						break;
					case 'way':
						$osmt = 'w';
						break;
					case 'relation':
						$osmt = 'r';
						break;
				}
				
				return 'http://osmose.openstreetmap.fr/fr/josm_proxy?load_object?objects='.$osmt.''.$this->id;
			}
			else
			{
				return 'https://www.openstreetmap.org/'.$this->type.'/'.$this->id;
			}
		}
		
		function getCenter()
		{
			if(is_null($this->center))
			{
				$bb = $this->getBoundingBox();
				$this->center = array(($bb[0][0]+$bb[1][0])/2., ($bb[0][1]+$bb[1][1])/2.);
			}
			
			return $this->center;
		}
		
		abstract function getBorder();
		abstract function getBoundingBox();
		
		function getName()
		{
			$ret = $this->getTag('name', true);
			
			if(!is_null($ret))
			{
				$ret = $ret[1];
				
				// If the name starts by the name of the operator, we remove it
				$operator = $this->getTag('operator', true);
				if(!is_null($operator))
				{
					$ret = str_replace($operator[1].' - ', '', $ret);
					$ret = str_replace($operator[1].', ', '', $ret);
					
					$pattern = '/'.$operator[1].' \((.*)\)/i';
					$replacement = '$1';
					$ret = preg_replace($pattern, $replacement, $ret);
				}
				
				return $ret;
			}
			
			return '';
		}
		
		function getAltNames()
		{
			$ret = array();
			
			$altName = $this->getTag('alt_name', true);
			if(!is_null($altName))
			{
				$ret[] = array($altName[1], $altName[2]);
			}
			
			return $ret;
		}
		
		function getWebsite()
		{
			$ret = $this->getTag('website');
			
			if(!is_null($ret))
				return $ret[1];
			
			return '';
		}
	}
	
	class Thrush_API_OpenStreetMap_Relation extends Thrush_OpenStreetMap_Object
	{
		protected $members = array();
		
		function __construct($id, $data, Thrush_API_OpenStreetMap $base)
		{
			Parent::__construct('relation', $id, $base);
			
			foreach($data['elements'] as $e)
			{
				if($e['type'] === 'relation' && $e['id'] == $id)
				{
					if(array_key_exists('tags', $e))
						$this->resetTags($e['tags']);
					
					foreach($e['members'] as $member)
					{
						if($member['type'] === 'node')
						{
							$this->addMember(new Thrush_API_OpenStreetMap_Node($member['ref'], $data, $base));
						}
						else if($member['type'] === 'way')
						{
							$this->addMember(new Thrush_API_OpenStreetMap_Way($member['ref'], $data, $base));
						}
						else if($member['type'] === 'relation')
						{
							$this->addMember(new Thrush_API_OpenStreetMap_Relation($member['ref'], $data, $base));
						}
					}
					
					break;
				}
			}
		}
		
		function addMember(Thrush_OpenStreetMap_Object $obj)
		{
			$this->members[] = $obj;
		}
		
		function getBorder()
		{
			if(is_null($this->border))
			{
				$this->border = array();
				
				foreach($this->members as $member)
				{
					$this->border = array_merge($this->border, $member->getBorder());
				}
			}
			
			return $this->border;
		}
		
		function getBoundingBox()
		{
			if(is_null($this->bbox) && !empty($this->members))
			{
				$this->bbox = $this->members[0]->getBoundingBox();
				
				foreach($this->members as $member)
				{
					$bbox = $member->getBoundingBox();
					
					if(is_null($bbox))
					{
						throw new Thrush_Exception('Error', 'Unable to get bbox for OSM object '.$member->getUid());
					}
					
					if($this->bbox[0][0] > $bbox[0][0])
						$this->bbox[0][0] = $bbox[0][0];
					if($this->bbox[1][0] < $bbox[1][0])
						$this->bbox[1][0] = $bbox[1][0];
					
					if($this->bbox[0][1] > $bbox[0][1])
						$this->bbox[0][1] = $bbox[0][1];
					if($this->bbox[1][1] < $bbox[1][1])
						$this->bbox[1][1] = $bbox[1][1];
				}
			}
			
			return $this->bbox;
		}
	}
	
	class Thrush_API_OpenStreetMap_Way extends Thrush_OpenStreetMap_Object
	{
		protected $nodes = array();
		
		function __construct($id, $data, Thrush_API_OpenStreetMap $base)
		{
			Parent::__construct('way', $id, $base);
			
			foreach($data['elements'] as $e)
			{
				if($e['type'] === 'way' && $e['id'] == $id)
				{
					if(array_key_exists('tags', $e))
						$this->resetTags($e['tags']);
					
					foreach($e['nodes'] as $nodeId)
					{
						$this->addNode(new Thrush_API_OpenStreetMap_Node($nodeId, $data, $base));
					}
					
					break;
				}
			}
		}
		
		function addNode(Thrush_API_OpenStreetMap_Node $node)
		{
			$this->nodes[] = $node;
		}
		
		function getBorder()
		{
			if(is_null($this->border))
			{
				$this->border = array();
				
				// 1 way = 1 polygon
				$polygon = array();
				foreach($this->nodes as $node)
				{
					$polygon[] = $node->getCenter();
				}
				$this->border[] = $polygon;
			}
			
			return $this->border;
		}
		
		function getBoundingBox()
		{
			if(is_null($this->bbox) && !empty($this->nodes))
			{
				$coord = $this->nodes[0]->getCenter();
				$this->bbox = array(
					$coord, 
					$coord);
				
				foreach($this->nodes as $node)
				{
					$coord = $node->getCenter();
					
					if(is_null($coord))
					{
						throw new Thrush_Exception('Error', 'Unable to get center for OSM object '.$node->getUid());
					}
					
					if($this->bbox[0][0] > $coord[0])
						$this->bbox[0][0] = $coord[0];
					if($this->bbox[1][0] < $coord[0])
						$this->bbox[1][0] = $coord[0];
					
					if($this->bbox[0][1] > $coord[1])
						$this->bbox[0][1] = $coord[1];
					if($this->bbox[1][1] < $coord[1])
						$this->bbox[1][1] = $coord[1];
				}
			}
			
			return $this->bbox;
		}
	}
	
	class Thrush_API_OpenStreetMap_Node extends Thrush_OpenStreetMap_Object
	{
		protected $coordinates = null;
		
		function __construct($id, $data, Thrush_API_OpenStreetMap $base)
		{
			Parent::__construct('node', $id, $base);
			
			foreach($data['elements'] as $e)
			{
				if($e['type'] === 'node' && $e['id'] == $id)
				{
					$this->center = array($e['lon'], $e['lat']);
					
					if(array_key_exists('tags', $e))
						$this->resetTags($e['tags']);
					
					break;
				}
			}
		}
		
		function getBorder()
		{
			if(is_null($this->border))
			{
				$this->border[] = array($this->getCenter());
			}
			
			return $this->border;
		}
		
		function getBoundingBox()
		{
			if(is_null($this->bbox))
			{
				$coord = $this->getCenter();
				$this->bbox = array(
					$coord, 
					$coord);
			}
			
			return $this->bbox;
		}
	}
?>