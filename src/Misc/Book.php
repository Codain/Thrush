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
	 
	
	class Thrush_Book
	{
		/**
		* Get ISBN-10 of the book.
		*
		* \return null|string
		*   ISBN-10 if one is available, null otherwise
		*/
		public function getIsbn10()
		{
			return null;
		}
		
		/**
		* Get ISBN-13 of the book.
		*
		* \return null|string
		*   ISBN-13 if one is available, null otherwise
		*/
		public function getIsbn13()
		{
			return null;
		}
		
		/**
		* Get Title of the book.
		*
		* \return null|string
		*   Title if one is available, null otherwise
		*/
		public function getTitle()
		{
			return null;
		}
		
		/**
		* Get array of authors of the book.
		*
		* \return array
		*   Set of authors
		*/
		public function getAuthors()
		{
			return null;
		}
		
		/**
		* Get URI to the biggest cover available.
		*
		* \return null|string
		*   URI if a cover is available, null otherwise
		*/
		public function getCover()
		{
			return null;
		}
		
		/**
		* Get Number of pages of the book.
		*
		* \return null|int
		*   Number of pages if one is available, null otherwise
		*/
		public function getNumberOfPages()
		{
			return null;
		}
		
		/**
		* Get Publication date of the book.
		*
		* \return null|DateTime|string
		*   Publication date if one is available, null otherwise
		*/
		public function getPublicationDate()
		{
			return null;
		}
		
		/**
		* Know if current book contains data.
		*
		* \return bool
		*   \c true if so, \c false otherwise
		*/
		public function isValid()
		{
			return false;
		}
	}
?>