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
	* Query Sirene server to get data
	* See https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee#/
	*/
	class Thrush_API_Sirene
	{
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		protected $cache = null;
		
		/**
		* string URL to Sirene server
		*/
		protected $endpointUrl = 'https://api.insee.fr/entreprises/sirene/V3/';
		
		/**
		* Headers to send with a request
		*/
		protected $headers = null;
		
		/**
		* Token to query the server
		*/
		protected $token = null;
		
		/**
		* Constructor.
		*
		* \param Thrush_Cache $cache
		*   Nominatim requires use of a Cache
		* \param string $token
		*   Sirene token
		*
		* \throws Thrush_Exception If no cache is provided
		* \throws Thrush_Exception If the email is not valid
		*/
		function __construct(Thrush_Cache $cache, string $token)
		{
			if(is_null($cache))
			{
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Sirene server');
			}
			
			$this->cache = $cache;
			
			$this->headers = array(
				'Authorization: Bearer '.$token,
				'Accept: application/json'
				);
		}
		
		/**
		* Query Sirene server for a specific SIREN.
		*
		* \param $id
		*   SIREN ID
		*
		* \return array|null
		*   JSON array of the result or null
		*/
		public function querySirenById($id)
		{
			$key = 'siren-'.$id.'.json';
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('sirene', $this->endpointUrl.'siren/'.$id, null, $key, Thrush_Cache::LIFE_IMMORTAL, '', null, $this->headers);
				
				$json = json_decode($data, true);
				
				return new Thrush_API_Sirene_UniteLegale($json['uniteLegale']);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
	}
	
	class Thrush_API_Sirene_UniteLegale
	{
		var $data = null;
		
		function __construct(array $data)
		{
			$this->data = $data;
		}
		
		function getInceptionDate()
		{
			$date = DateTime::createFromFormat('Y-m-d', $this->data['dateCreationUniteLegale']);
			
			return $date;
		}
		
		function getLegalCategory()
		{
			return $this->data['periodesUniteLegale'][0]['categorieJuridiqueUniteLegale'];
		}
		
		function getNumberEmployees()
		{
			$nb = $this->data['trancheEffectifsUniteLegale'];
			
			if(is_null($nb))
				return '0';
			
			$descriptionTranches = array(
				'NN' => '0',
				'00' => '0',
				'01' => '1-2',
				'02' => '3-5',
				'03' => '6-9',
				'11' => '10-19',
				'12' => '20-49',
				'21' => '50-99',
				'22' => '100-199',
				'31' => '200-249',
				'32' => '250-499',
				'41' => '500-999',
				'42' => '1 000-1 999',
				'51' => '2 000-4 999',
				'52' => '5 000-9 999',
				'53' => '10 000+');
			
			return $descriptionTranches[$nb];
		}
		
		function getDateNumberEmployees()
		{
			$date = new DateTime();
			$date->setDate($this->data['anneeEffectifsUniteLegale'], 12, 31);
			
			return $date;
		}
	}
?>