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
	
	/**
	* Query a Nominatim server to get data
	*/
	class Thrush_API_Nominatim
	{
		const CALLBACK_DEDUCE_CONTINENT = 1;
		
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
		* \param int $flags
		*   Callback flags (optional, default 0).
		*
		* \return array|null
		*   JSON array of the result (see https://nominatim.org/release-docs/develop/api/Lookup/) or null
		*/
		public function queryByObject(Thrush_OpenStreetMap_Object $obj, array $acceptLanguages=null, int $flags=0)
		{
			$res = null;
			$forceByCoordinate = false;
			do
			{
				if($forceByCoordinate || (!$obj->hasTag('name') && !$obj->hasTag('addr:housenumber')))
				{
					$coordinates = $obj->getCenter();
					
					if(!is_null($coordinates))
					{
						$res = $this->queryByCoordinate($coordinates[0], $coordinates[1], $acceptLanguages, $flags);
					}
					
					$forceByCoordinate = false;
				}
				else
				{
					$res = $this->queryByObjectsId(array($obj->getUid()), $acceptLanguages, $flags);
					if(!is_null($res) && !empty($res))
					{
						$res = $res[0];
					}
					else
					{
						$forceByCoordinate = true;
					}
				}
			} while($forceByCoordinate);
			
			return $res;
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
		* \param int $flags
		*   Callback flags (optional, default 0).
		*
		* \return array|null
		*   JSON array of the result (see https://nominatim.org/release-docs/develop/api/Lookup/) or null
		*/
		public function queryByObjectsId(array $keys, array $acceptLanguages=null, int $flags=0)
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
			$key = 'ids_'.$attributes['osm_ids'];
				
			if(!is_null($acceptLanguages))
			{
				$attributes['accept-language'] = implode(',', $acceptLanguages);
				$key .= '_'.$attributes['accept-language'];
			}
			$key .= '.json';
			
			$queryString = http_build_query($attributes);
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('nominatim', $endpointUrl.'?'.$queryString, null, $key, Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'addressArrayCallback'), $flags));
				
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
		* \param int $flags
		*   Callback flags (optional, default 0).
		*
		* \return array|null
		*   JSON array of the result (see https://nominatim.org/release-docs/develop/api/Reverse/) or null
		*/
		public function queryByCoordinate(float $lon, float $lat, array $acceptLanguages=null, int $flags=0)
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
			$key = 'coord_'.$lat.'_'.$lon;
				
			if(!is_null($acceptLanguages))
			{
				$attributes['accept-language'] = implode(',', $acceptLanguages);
				$key .= '_'.$attributes['accept-language'];
			}
			$key .= '.json';
			
			$queryString = http_build_query($attributes);
			
			try
			{
				// Fetch data
				$data = $this->cache->loadURLFromWebOrCache('nominatim', $endpointUrl.'?'.$queryString, null, $key, Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'addressCallback'), $flags));
			
				return json_decode($data, true);
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
		
		protected function deduceContinent(array $place)
		{
			static $isoCountries = array(
				'ad' => 'Europe', // Andorra
				'ae' => 'Asia', // United Arab Emirates
				'af' => 'Asia', // Afghanistan
				'ag' => 'North America', // Antigua and Barbuda
				'al' => 'Europe', // Albania
				'am' => 'Asia', // Armenia
				'am' => 'Europe', // Armenia
				'ao' => 'Africa', // Angola
				'ar' => 'South America', // Argentina
				'as' => 'Oceania', // American Samoa
				'at' => 'Europe', // Austria
				'au' => 'Oceania', // Australia
				'aw' => 'South America', // Aruba
				'az' => 'Asia', // Azerbaijan
				'az' => 'Europe', // Azerbaijan
				'ba' => 'Europe', // Bosnia and Herzegovina
				'bb' => 'North America', // Barbados
				'bd' => 'Asia', // Bangladesh
				'be' => 'Europe', // Belgium
				'bf' => 'Africa', // Burkina Faso
				'bg' => 'Europe', // Bulgaria
				'bh' => 'Asia', // Bahrain
				'bi' => 'Africa', // Burundi
				'bj' => 'Africa', // Benin
				'bn' => 'Asia', // Brunei
				'bo' => 'South America', // Bolivia
				'br' => 'South America', // Brazil
				'bs' => 'North America', // The Bahamas
				'bt' => 'Asia', // Bhutan
				'bw' => 'Africa', // Botswana
				'by' => 'Europe', // Belarus
				'bz' => 'North America', // Belize
				'ca' => 'North America', // Canada
				'cd' => 'Africa', // Democratic Republic of the Congo
				'cf' => 'Africa', // Central African Republic
				'cg' => 'Africa', // Republic of the Congo
				'ch' => 'Europe', // Switzerland
				'ci' => 'Africa', // Ivory Coast
				'ck' => 'Oceania', // Cook Islands
				'cl' => 'South America', // Chile
				'cm' => 'Africa', // Cameroon
				'cn' => 'Asia', // People's Republic of China
				'co' => 'South America', // Colombia
				'cr' => 'North America', // Costa Rica
				'cu' => 'North America', // Cuba
				'cv' => 'Africa', // Cape Verde
				'cy' => 'Europe', // Cyprus
				'cy' => 'Asia', // Cyprus
				'cz' => 'Europe', // Czech Republic
				'de' => 'Europe', // Germany
				'dj' => 'Africa', // Djibouti
				'dk' => 'Europe', // Denmark for Denmark, Faroe Islands
				//'dk' => 'North America', // Denmark for Greenland
				'dm' => 'North America', // Dominica
				'do' => 'North America', // Dominican Republic
				'dz' => 'Africa', // Algeria
				'ec' => 'South America', // Ecuador
				'ee' => 'Europe', // Estonia
				//'eg' => 'Asia', // Egypt for Sinai Peninsula
				'eg' => 'Africa', // Egypt
				'er' => 'Africa', // Eritrea
				//'es' => 'Africa', // Spain for Canary Islands, Ceuta, Melilla, Plazas de soberanÃ­a
				'es' => 'Europe', // Spain for Balearic Islands, peninsular Spain
				'et' => 'Africa', // Ethiopia
				'fi' => 'Europe', // Finland
				'fj' => 'Oceania', // Fiji
				'fm' => 'Oceania', // Federated States of Micronesia
				'fo' => 'Europe', // Faroe Islands
				//'fr' => 'Americas', // France for French Guiana, Guadeloupe, Martinique, Saint BarthÃ©lemy, Saint Pierre and Miquelon, Saint Martin (French part), Clipperton Island
				//'fr' => 'Antarctica', // France for French Southern and Antarctic Lands, AdÃ©lie Land
				//'fr' => 'Africa', // France for Mayotte, RÃ©union, Scattered Islands in the Indian Ocean
				//'fr' => 'Oceania', // France for French Polynesia, New Caledonia, Wallis and Futuna
				'fr' => 'Europe', // France for Metropolitan France
				'ga' => 'Africa', // Gabon
				'gb' => 'Europe', // United Kingdom
				'gd' => 'North America', // Grenada
				'ge' => 'Asia', // Georgia
				'ge' => 'Europe', // Georgia
				'gh' => 'Africa', // Ghana
				'gl' => 'North America', // Greenland
				'gm' => 'Africa', // The Gambia
				'gn' => 'Africa', // Guinea
				'gq' => 'Africa', // Equatorial Guinea
				'gr' => 'Europe', // Greece
				'gt' => 'North America', // Guatemala
				'gw' => 'Africa', // Guinea-Bissau
				'gy' => 'South America', // Guyana
				'hn' => 'North America', // Honduras
				'hr' => 'Europe', // Croatia
				'ht' => 'North America', // Haiti
				'hu' => 'Europe', // Hungary
				'id' => 'Asia', // Indonesia
				'ie' => 'Europe', // Ireland
				'il' => 'Asia', // Israel
				'in' => 'Asia', // India
				'iq' => 'Asia', // Iraq
				'ir' => 'Asia', // Iran
				'is' => 'Europe', // Iceland
				'it' => 'Europe', // Italy
				//'it' => 'Africa', // Italy for Pelagie Islands
				'jm' => 'North America', // Jamaica
				'jo' => 'Asia', // Jordan
				'jp' => 'Asia', // Japan
				'ke' => 'Africa', // Kenya
				'kg' => 'Asia', // Kyrgyzstan
				'kh' => 'Asia', // Cambodia
				'ki' => 'Oceania', // Kiribati
				'km' => 'Africa', // Comoros
				'kn' => 'North America', // Saint Kitts and Nevis
				'kp' => 'Asia', // North Korea
				'kr' => 'Asia', // South Korea
				'kw' => 'Asia', // Kuwait
				//'kz' => 'Europe', // Kazakhstan for European Kazakhstan
				'kz' => 'Asia', // Kazakhstan
				'la' => 'Asia', // Laos
				'lb' => 'Asia', // Lebanon
				'lc' => 'North America', // Saint Lucia
				'li' => 'Europe', // Liechtenstein
				'lk' => 'Asia', // Sri Lanka
				'lr' => 'Africa', // Liberia
				'ls' => 'Africa', // Lesotho
				'lt' => 'Europe', // Lithuania
				'lu' => 'Europe', // Luxembourg
				'lv' => 'Europe', // Latvia
				'ly' => 'Africa', // Libya
				'ma' => 'Africa', // Morocco
				'mc' => 'Europe', // Monaco
				'md' => 'Europe', // Moldova
				'me' => 'Europe', // Montenegro
				'mg' => 'Africa', // Madagascar
				'mh' => 'Oceania', // Marshall Islands
				'mk' => 'Europe', // North Macedonia
				'ml' => 'Africa', // Mali
				'mm' => 'Asia', // Myanmar
				'mn' => 'Asia', // Mongolia
				'mp' => 'Oceania', // Northern Mariana Islands
				'mr' => 'Africa', // Mauritania
				'mt' => 'Europe', // Malta
				'mu' => 'Africa', // Mauritius
				'mv' => 'Asia', // Maldives
				'mw' => 'Africa', // Malawi
				'mx' => 'North America', // Mexico
				'my' => 'Asia', // Malaysia
				'mz' => 'Africa', // Mozambique
				'na' => 'Africa', // Namibia
				'ne' => 'Africa', // Niger
				'ng' => 'Africa', // Nigeria
				'ni' => 'North America', // Nicaragua
				//'nl' => 'Central America', // Netherlands for Caribbean Netherlands
				'nl' => 'Europe', // Netherlands for European Netherlands
				//'nl' => 'South America', // Kingdom of the Netherlands for Dutch colonisation of the Guianas
				//'nl' => 'Asia', // Kingdom of the Netherlands for Netherlands New Guinea
				//'nl' => 'Africa', // Kingdom of the Netherlands
				//'nl' => 'Europe', // Kingdom of the Netherlands
				//'nl' => 'North America', // Kingdom of the Netherlands
				'no' => 'Europe', // Norway for Jan Mayen, Svalbard, mainland Norway
				//'no' => 'Antarctica', // Norway for Bouvet Island, Queen Maud Land, Peter I Island
				'np' => 'Asia', // Nepal
				'nr' => 'Oceania', // Nauru
				'nu' => 'Oceania', // Niue
				'nz' => 'Oceania', // New Zealand
				'om' => 'Asia', // Oman
				'pa' => 'North America', // Panama
				'pa' => 'South America', // Panama
				'pe' => 'South America', // Peru
				'pg' => 'Oceania', // Papua New Guinea
				'ph' => 'Asia', // Philippines
				'pk' => 'Asia', // Pakistan
				'pl' => 'Europe', // Poland
				'pt' => 'Europe', // Portugal
				'pw' => 'Oceania', // Palau
				'py' => 'South America', // Paraguay
				'qa' => 'Asia', // Qatar
				'ro' => 'Europe', // Romania
				'rs' => 'Europe', // Serbia
				'ru' => 'Asia', // Russia for Asian Russia
				//'ru' => 'Europe', // Russia for European Russia
				'rw' => 'Africa', // Rwanda
				'sa' => 'Asia', // Saudi Arabia
				'sb' => 'Oceania', // Solomon Islands
				'sc' => 'Africa', // Seychelles
				'sd' => 'Africa', // Sudan
				'se' => 'Europe', // Sweden
				'sg' => 'Asia', // Singapore
				'si' => 'Europe', // Slovenia
				'sk' => 'Europe', // Slovakia
				'sl' => 'Africa', // Sierra Leone
				'sm' => 'Europe', // San Marino
				'sn' => 'Africa', // Senegal
				'so' => 'Africa', // Somalia
				'sr' => 'South America', // Suriname
				'ss' => 'Africa', // South Sudan
				'st' => 'Africa', // SÃ£o TomÃ© and PrÃ­ncipe
				'sv' => 'North America', // El Salvador
				'sx' => 'North America', // Sint Maarten
				'sy' => 'Asia', // Syria
				'sz' => 'Africa', // Eswatini
				'td' => 'Africa', // Chad
				'tg' => 'Africa', // Togo
				'th' => 'Asia', // Thailand
				'tj' => 'Asia', // Tajikistan
				'tl' => 'Asia', // East Timor
				'tm' => 'Asia', // Turkmenistan
				'tn' => 'Africa', // Tunisia
				'to' => 'Oceania', // Tonga
				'tr' => 'Asia', // Turkey for Anatolia
				//'tr' => 'Europe', // Turkey for East Thrace
				'tt' => 'North America', // Trinidad and Tobago
				'tv' => 'Oceania', // Tuvalu
				'tw' => 'Asia', // Taiwan
				'tz' => 'Africa', // Tanzania
				'ua' => 'Europe', // Ukraine
				'ug' => 'Africa', // Uganda
				//'us' => 'Asia', // United States of America for Commonwealth of the Philippines
				//'us' => 'Oceania', // United States of America for Hawaii, Guam, American Samoa, Northern Mariana Islands
				'us' => 'North America', // United States of America for Alaska, Puerto Rico, United States Virgin Islands, contiguous United States
				'uy' => 'South America', // Uruguay
				'uz' => 'Asia', // Uzbekistan
				'va' => 'Europe', // Vatican City
				'vc' => 'North America', // Saint Vincent and the Grenadines
				've' => 'South America', // Venezuela
				'vn' => 'Asia', // Vietnam
				'vu' => 'Oceania', // Vanuatu
				'ws' => 'Oceania', // Samoa
				'ye' => 'Asia', // Yemen
				//'ye' => 'Africa', // Yemen for Socotra Archipelago
				'za' => 'Africa', // South Africa
				'zm' => 'Africa', // Zambia
				'zw' => 'Africa' // Zimbabwe
				);
			
			$continent = 'Unknown';
			
			if(array_key_exists('country_code', $place['address']))
			{
				if($place['address']['country_code'] === 'fr' && array_key_exists('state', $place['address']) && $place['address']['state'] === 'French Guiana')
				{
					$continent = 'South America';
				}
				/*if($place['address']['country_code'] === 'fr' && $place['lon'] < -29.36 && $place['lat'] < 26.27)
				{
					$continent = 'South America';
				}
				else if($place['address']['country_code'] === 'fr' && $place['lon'] < -29.36 && $place['lat'] >= 26.27)
				{
					$continent = 'North America';
				}
				else if($place['address']['country_code'] === 'fr' && $place['lon'] > 95.8)
				{
					$continent = 'Oceania';
				}*/
				else if(array_key_exists($place['address']['country_code'], $isoCountries))
				{
					$continent = $isoCountries[$place['address']['country_code']];
				}
			}
			else if($place['lat'] < -59)
			{
				$continent = 'Antartica';
			}
			
			return $continent;
		}
		
		public function addressCallback(string &$data, int $flags=0)
		{
			if($flags !== 0)
			{
				$result = json_decode($data, true);
				
				if($flags & self::CALLBACK_DEDUCE_CONTINENT)
				{
					if(!array_key_exists('continent', $result['address']))
						$result['address']['continent'] = $this->deduceContinent($result);
				}
				
				$data = json_encode($result);
			}
		}
		
		public function addressArrayCallback(string &$data, int $flags=0)
		{
			if($flags !== 0)
			{
				$result = json_decode($data, true);
				
				if($flags & self::CALLBACK_DEDUCE_CONTINENT)
				{
					foreach($result as $key => &$item)
					{
						if(!array_key_exists('continent', $item['address']))
							$item['address']['continent'] = $this->deduceContinent($item);
					}
				}
				
				$data = json_encode($result);
			}
		}
	}
?>