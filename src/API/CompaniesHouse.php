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
	* Query Companies House server to get data
	* See https://developer-specs.company-information.service.gov.uk/
	*/
	class Thrush_API_CompaniesHouse
	{
		/**
		* Thrush_Cache Pointer to a Thrush_Cache object
		*/
		protected $cache = null;
		
		/**
		* string URL to Companies House server
		*/
		protected $endpointUrl = 'https://api.company-information.service.gov.uk/';
		
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
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query Companies House server');
			}
			
			$this->cache = $cache;
			
			// Token provided by CompaniesHouse is username for a basic Authorization and no password is needed
			$this->headers = array(
				'Authorization: Basic '.base64_encode($token.':'),
				//'Authorization: Basic '.$token,
				//'Authorization: Bearer '.$token,
				'Content-Type: text/json'
				);
		}
		
		/**
		* Query Companies House server for a specific Company.
		*
		* \param $id
		*   Companies House number
		*
		* \return array|null
		*   JSON array of the result or null
		*/
		public function queryCompanyById($id)
		{
			$key = 'company-'.$id.'.json';
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('companies-house', $this->endpointUrl.'company/'.$id, null, $key, Thrush_Cache::LIFE_IMMORTAL, '', null, $this->headers);
				
				$json = json_decode($data, true);
				
				return new Thrush_API_CompaniesHouse_Company($json);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
	}
	
	// https://developer-specs.company-information.service.gov.uk/companies-house-public-data-api/resources/companyprofile?v=latest
	class Thrush_API_CompaniesHouse_Company
	{
		var $data = null;
		
		function __construct(array $data)
		{
			$this->data = $data;
		}
		
		function getDateOfCreation()
		{
			$date = DateTime::createFromFormat('Y-m-d H:i:s', $this->data['date_of_creation'].' 00:00:00');
			
			return $date;
		}
		
	}
?>