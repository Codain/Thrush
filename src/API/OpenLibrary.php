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
	require_once __DIR__.'/../Misc/Book.php';
	
	/**
	* Query OpenLibrary server to get data
	*/
	class Thrush_API_OpenLibrary
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
				throw new Thrush_Exception('Error', 'A Cache is mandatory to query OpenLibrary server');
			}
			
			$this->cache = $cache;
		}
		
		/**
		* Query OpenLibrary server for informations on a single book based on its OpenLibrary Id, see https://openlibrary.org/dev/docs/api/books.
		*
		* \param string $olid
		*   OpenLibrary Id to look for
		* \param array $overrideAttributes
		*   List of attributes to customize the query
		*
		* \return array
		*   Array of attributes
		*/
		public function queryBook(string $olid, array $overrideAttributes=array())
		{
			$endpointUrl = 'https://openlibrary.org/api/books';
			
			// Generate URL-encoded query string
			// See https://openlibrary.org/dev/docs/api/books
			$attributes = array(
				'format' => 'json',
				'jscmd' => 'data',
				'bibkeys' => 'OLID:'.$olid
				);
			
			// Merge with user defined attributes
			$attributes = array_merge($attributes, $overrideAttributes);
			
			$queryString = http_build_query($attributes);
			
			// Fetch data
			try
			{
				$data = $this->cache->loadURLFromWebOrCache('openlibrary', $endpointUrl.'?'.$queryString, null, $olid.'.json', Thrush_Cache::LIFE_IMMORTAL);
				$data = json_decode($data, true);
				
				if(array_key_exists('OLID:'.$olid, $data))
					return new Thrush_API_OpenLibrary_Book($data['OLID:'.$olid]);
				
				return new Thrush_API_OpenLibrary_Book();
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
		
		/**
		* Query OpenLibrary server for informations on a single book based on its ISBN, see https://openlibrary.org/dev/docs/api/books.
		*
		* \param string $isbn
		*   ISBN (10 or 13 digits) to look for
		* \param array $overrideAttributes
		*   List of attributes to customize the query
		*
		* \return array
		*   Array of attributes
		*/
		public function queryBookByIsbn(string $isbn, array $overrideAttributes=array())
		{
			$endpointUrl = 'https://openlibrary.org/api/books';
			
			// Generate URL-encoded query string
			// See https://openlibrary.org/dev/docs/api/books
			$attributes = array(
				'format' => 'json',
				'jscmd' => 'data',
				'bibkeys' => 'ISBN:'.$isbn
				);
			
			// Merge with user defined attributes
			$attributes = array_merge($attributes, $overrideAttributes);
			
			$queryString = http_build_query($attributes);
			
			// Fetch data
			try
			{
				$data = $this->cache->loadURLFromWebOrCache('openlibrary', $endpointUrl.'?'.$queryString, null, $isbn.'.json', Thrush_Cache::LIFE_IMMORTAL);
				$data = json_decode($data, true);
				
				if(array_key_exists('ISBN:'.$isbn, $data))
					return new Thrush_API_OpenLibrary_Book($data['ISBN:'.$isbn]);
				
				return new Thrush_API_OpenLibrary_Book();
			}
			catch(Thrush_Cache_NoDataToLoadException $e)
			{
				return null;
			}
		}
	}
	
	/**
	* Object to represent an OpenLibrary Book
	*/
	class Thrush_API_OpenLibrary_Book extends Thrush_Book
	{
		/**
		* array Result from OpenLibrary
		*/
		protected $data = null;
		
		/**
		* Constructor.
		*
		* \param array $data
		*   Result from OpenLibrary
		*/
		function __construct(array $data=null)
		{
			$this->data = $data;
		}
		
		/**
		* Know if current book contains data.
		*
		* \return bool
		*   \c true if so, \c false otherwise
		*/
		public function isValid()
		{
			return !is_null($this->data);
		}
		
		/**
		* Get ISBN-10 of the book.
		*
		* \return null|string
		*   ISBN-10 if one is available, null otherwise
		*/
		public function getIsbn10()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('identifiers', $this->data) || !array_key_exists('isbn_10', $this->data['identifiers']))
				return null;
			
			return $this->data['identifiers']['isbn_10'][0];
		}
		
		/**
		* Get ISBN-13 of the book.
		*
		* \return null|string
		*   ISBN-13 if one is available, null otherwise
		*/
		public function getIsbn13()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('identifiers', $this->data) || !array_key_exists('isbn_13', $this->data['identifiers']))
				return null;
			
			return $this->data['identifiers']['isbn_13'][0];
		}
		
		/**
		* Get OpenLibrary ID of the book.
		*
		* \return null|string
		*   OpenLibrary ID if one is available, null otherwise
		*/
		public function getOpenLibraryId()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('identifiers', $this->data) || !array_key_exists('openlibrary', $this->data['identifiers']))
				return null;
			
			return $this->data['identifiers']['openlibrary'][0];
		}
		
		/**
		* Get OpenLibrary URL of the book.
		*
		* \return null|string
		*   OpenLibrary URL if one is available, null otherwise
		*/
		public function getOpenLibraryUrl()
		{
			if(is_null($this->data))
				return null;
			
			return $this->data['url'];
		}
		
		/**
		* Get Title of the book.
		*
		* \return null|string
		*   Title if one is available, null otherwise
		*/
		public function getTitle()
		{
			if(is_null($this->data))
				return '';
			
			if(!array_key_exists('title', $this->data))
				return '';
			
			return $this->data['title'];
		}
		
		/**
		* Get array of authors of the book.
		*
		* \return array
		*   Set of Thrush_API_OpenLibrary_Author
		*/
		public function getAuthors()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('authors', $this->data))
				return array();
			
			$ret = array();
			foreach($this->data['authors'] as $author)
			{
				$ret[] = new Thrush_API_OpenLibrary_Author($author);
			}
			
			return $ret;
		}
		
		/**
		* Get URI to the biggest cover available.
		*
		* \return null|string
		*   URI if a cover is available, null otherwise
		*/
		public function getCover()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('cover', $this->data))
				return null;
			
			return $this->data['cover']['large'];
		}
		
		/**
		* Get Number of pages of the book.
		*
		* \return null|int
		*   Number of pages if one is available, null otherwise
		*/
		public function getNumberOfPages()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('number_of_pages', $this->data))
				return null;
			
			return $this->data['number_of_pages'];
		}
		
		/**
		* Get Publication date of the book.
		*
		* \return null|DateTime|string
		*   Publication date if one is available, null otherwise
		*/
		public function getPublicationDate()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('publish_date', $this->data))
				return null;
			
			//return DateTime::createFromFormat('M j, Y', $this->data['publish_date']);
			return $this->data['publish_date'];
		}
	}
	
	/**
	* Object to represent an OpenLibrary Author
	*/
	class Thrush_API_OpenLibrary_Author
	{
		/**
		* array Result from OpenLibrary
		*/
		protected $data = null;
		
		/**
		* Constructor.
		*
		* \param array $data
		*   Result from OpenLibrary
		*/
		function __construct(array $data=null)
		{
			$this->data = $data;
			
			$this->data['id'] = null;
			if(array_key_exists('url', $this->data))
			{
				$uid = str_replace('https://openlibrary.org/authors/', '', $this->data['url']);
				$this->data['id'] = substr($uid, 0, strpos($uid, '/'));
			}
		}
		
		/**
		* Get OpenLibrary ID of the author.
		*
		* \return null|string
		*   OpenLibrary ID if one is available, null otherwise
		*/
		public function getOpenLibraryId()
		{
			if(is_null($this->data))
				return null;
			
			return $this->data['id'];
		}
		
		/**
		* Get OpenLibrary URL of the author.
		*
		* \return null|string
		*   OpenLibrary URL if one is available, null otherwise
		*/
		public function getOpenLibraryUrl()
		{
			if(is_null($this->data))
				return null;
			
			return $this->data['url'];
		}
		
		/**
		* Get full name of the author.
		*
		* \return null|string
		*   Full name of the author if one is available, null otherwise
		*/
		public function getFullName()
		{
			if(is_null($this->data))
				return null;
			
			if(!array_key_exists('name', $this->data))
				return null;
			
			return $this->data['name'];
		}
	}
?>