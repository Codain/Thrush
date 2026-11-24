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
	 
	require_once __DIR__.'/../WebPageLoader.php';
	require_once __DIR__.'/../Exception.php';
	
	/**
	* Query TheSpaceDevs Launch Library 2 server to get data
	*/
	class Thrush_API_LaunchLibrary2
	{
		/**
		* Thrush_WebPageLoader Pointer to a Thrush_WebPageLoader object
		*/
		protected Thrush_WebPageLoader $webPageLoader;
		
		/**
		* string URL to TheSpaceDevs Launch Library 2 server
		*/
		protected string $endpointUrl = 'https://ll.thespacedevs.com/2.0.0/';
		
		/**
		* Constructor.
		*
		* \param Thrush_WebPageLoader $webPageLoader
		*   Object to call for the API
		*
		* \throws Thrush_Exception If no cache is provided
		* \throws Thrush_Exception If the email is not valid
		*/
		function __construct(Thrush_WebPageLoader $webPageLoader)
		{
			$this->webPageLoader = $webPageLoader;
		}
		
		/**
		* Query TheSpaceDevs launch library 2 server for a specific location.
		*
		* \param int $id
		*   Location ID
		*
		* \return array|null
		*   JSON array of the result or null
		*/
		public function queryLocation(int $id): ?array
		{
			$key = 'location-'.$id.'.json';
			
			try
			{
				// Fetch data
				return $this->webPageLoader->loadURLAsJSONAssociative($this->endpointUrl.'location/'.$id.'/', null, $key, Thrush_Cache::LIFE_IMMORTAL, '', null);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
		
		/**
		* Query TheSpaceDevs launch library 2 server for all pads.
		*
		* \param array $iteratorCallback
		*   Array made of a callback and arguments to call for each pad
		*
		* \return int
		*   Number of pads
		*/
		public function queryPads(array $iteratorCallback): int
		{
			return $this->queryItemsInc('pad/', 100, $iteratorCallback);
		}
		
		/**
		* Query TheSpaceDevs launch library 2 server for all upcoming launches.
		*
		* \param array $iteratorCallback
		*   Array made of a callback and arguments to call for each launch
		*
		* \return int
		*   Number of upcoming launches
		*/
		public function queryUpcomingLaunches(array $iteratorCallback): int
		{
			return $this->queryItemsInc('launch/upcoming/', 100, $iteratorCallback);
		}
		
		/**
		* Run a query which can return several items as results.
		*
		* \param string $dir
		*   Query to add to endpoint (e.g. 'launch/upcoming/')
		* \param int $limit
		*   Number of items in each results (e.g. 10)
		* \param array $iteratorCallback
		*   Array made of a callback and arguments to call for each item
		*
		* \return int
		*   Number of items
		*/
		protected function queryItemsInc(string $dir, int $limit, array $iteratorCallback): int
		{
			$nb = 0;
			
			if(is_null($iteratorCallback))
				throw new Thrush_Exception('Error', 'A callback iterator shall be given');
			
			try
			{
				$i = 1;
				$url = $this->endpointUrl.$dir.'?limit='.$limit;
				while($url != null)
				{
					$data = $this->webPageLoader->loadURLAsJSONAssociative($url, null, str_replace('/', '-', $dir).'list_'.$i.'.json', Thrush_Cache::LIFE_IMMORTAL, '', null, array('accept: application/json'));
					
					foreach($data['results'] as $launch)
					{
						// From Thrush_Cache
						$args = $iteratorCallback;
						$args[0] =& $launch;
						call_user_func_array($iteratorCallback[0], $args);
						
						$nb++;
					}
					
					// Prepare next call
					$url = $data['next'];
					$i++;
				}
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				// Do nothing?
			}
			
			return $nb;
		}
	}
?>