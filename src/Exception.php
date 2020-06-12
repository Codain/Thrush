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
	
	/**
	* A Thrush_Exception brings additional functions to baseline Exception class.
	*/
	class Thrush_Exception extends Exception
	{
		/**
		* string Private debug message, for administrators only.
		*/
		protected $privateMessage = '';
		
		/**
		* bool Set wether to display backtrace or not.
		*/
		protected $showTrace = true;
		
		/**
		* Thrush_Exception constructor
		*
		* \param string $publicMessage
		*   The public error message
		* \param string $privateMessage
		*   The private error message
		* \param int $code
		*   The exception code
		*/
		function __construct(string $publicMessage='', string $privateMessage='', int $code=0)
		{
			parent::__construct($publicMessage, $code);
			
			if($privateMessage === '')
				$this->privateMessage = $publicMessage;
			else
				$this->privateMessage = $privateMessage;
		}
		
		/**
		* Retrieve an error message which can be displayed to public.
		* It will not contains security/sensitive details on the error.
		*
		* \return string
		*   The public message
		
		* \see getPublicMessage()
		*/
		function getPrivateMessage()
		{
			return $this->privateMessage;
		}
		
		/**
		* Retrieve an error message which can be displayed to public.
		* It will not contains security/sensitive details on the error.
		*
		* \return string
		*   The public message
		
		* \see getPrivateMessage()
		*/
		function getPublicMessage()
		{
			return $this->getMessage();
		}
		
		/**
		* Retrieve the error message of an Exception, the private one if it 
		* exists, otherwise the public one.
		*
		* \param Throwable $e
		*   The Exception
		* 
		* \return string
		*   The message
		*/
		static function getPrivateMessageIfAny(Throwable $e)
		{
			if($e instanceof Thrush_Exception)
			{
				return trim($e->getPrivateMessage());
			}
			
			return trim($e->getMessage());
		}
		
		/**
		* Retrieve the trace of an Exception.
		*
		* \param Throwable $e
		*   The Exception
		* \param array $seen
		*   Array of files and lines seen to avoid recursion (internal 
		*   variable, always set to null)
		* 
		* \return string
		*   The trace
		*/
		static function formatToString(Throwable $e, array $seen=null)
		{
			$starter = $seen ? 'Caused by: ' : '';
			$result = array();
			
			if (!$seen)
				$seen = array();
			
			$trace  = $e->getTrace();
			$prev   = $e->getPrevious();
			$result[] = sprintf('%s%s: %s', $starter, get_class($e), Thrush_Exception::getPrivateMessageIfAny($e));
			
			// Display backtrace only if requested
			if(!($e instanceof Thrush_Exception) || $e->showTrace)
			{
				$file = $e->getFile();
				$line = $e->getLine();
				
				while (true)
				{
					$current = "$file:$line";
					/*if (is_array($seen) && in_array($current, $seen)) {
						$result[] = sprintf(' ... %d more', count($trace)+1);
						break;
					}*/
					$result[] = sprintf(' at %s%s%s(%s%s%s)',
												count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
												count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
												count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
												$line === null ? $file : basename($file),
												$line === null ? '' : ':',
												$line === null ? '' : $line);
					if(is_array($seen))
						$seen[] = "$file:$line";
					
					if(!count($trace))
						break;
					
					$file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
					$line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
					array_shift($trace);
				}
			}
			
			$result = join("\n", $result);
			
			if($prev)
				$result .= "\n" . Thrush_Exception::formatToString($prev, $seen);

			return $result;
		}
	}
	
	/**
	* A Thrush_HTTPException brings additional functions specific to HTTP Exceptions
	*/
	class Thrush_HTTPException extends Thrush_Exception
	{
		/**
		* array Array of headers
		*/
		protected $response = null;
		
		/**
		* Constructor.
		*
		* \param string url
		*   URL of the ressource
		* \param string http_headers
		*   HTTP headers to analyse
		*/
		function __construct(string $url, array $http_headers)
		{
			parent::__construct('Error', '', 0);
			
			$this->response = $http_headers;
			
			$this->privateMessage = "Error ".$this->getHTTPCode()." when calling ".$url.": ".$this->response[0];
		}
		
		/**
		* Get HTTP return code.
		*
		* \return int
		*   HTTP error code
		*/
		function getHTTPCode()
		{
			return $this->response['response_code'];
		}
		
		/**
		* Parse HTTP header answer to extract data.
		*
		* \param string url
		*   URL of the ressource
		* \param array headers
		*   HTTP headers
		*
		* \return array
		*   Array of data
		*/
		static function parseHeaders(string $url, array $headers)
		{
			$head = array();
			
			foreach( $headers as $k=>$v )
			{
				$t = explode( ':', $v, 2 );
				if( isset( $t[1] ) )
				{
					$head[ trim($t[0]) ] = trim( $t[1] );
				}
				else
				{
					if(preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out))
					{
						$head['response_code'] = intval($out[1]);
					}
					
					$head[] = $v;
				}
			}
			
			if(!array_key_exists('response_code', $head))
			{
				throw new Thrush_Exception('Error', 'Unnable to extract return code when calling '.$url.': '.print_r($headers, true));
			}
			
			return $head;
		}
	}
	
	/**
	* A Thrush_CurlException brings additional functions specific to HTTP Exceptions
	*/
	class Thrush_CurlException extends Thrush_Exception
	{
		/**
		* array Array of cURL informations
		*/
		protected $response = null;
		
		/**
		* Constructor.
		*
		* \param ressource cURL object
		*   cURL object to analyse
		*/
		function __construct(ressource $curl, string $data)
		{
			parent::__construct('Error', '', 0);
			
			$this->response = curl_getinfo($curl);
			
			$this->privateMessage = "Error ".$this->getHTTPCode()." when calling ".$this->response['url'].": ".$data;
		}
		
		/**
		* Get HTTP return code.
		*
		* \return int
		*   HTTP error code
		*/
		function getHTTPCode()
		{
			return $this->response['http_code'];
		}
	}
	
	/**
	* A Thrush_DeprecatedException warns about deprecated functions or piece of code
	*/
	class Thrush_DeprecatedException extends Thrush_Exception
	{
		/**
		* Constructor.
		*/
		function __construct()
		{
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			
			$where = $trace[1];
			
			$msg = sprintf('%s() is deprecated, called from %s:%d', (array_key_exists('class', $where)?$where['class'].'::':'').$where['function'], $where['file'], $where['line']);
			
			parent::__construct('Error', $msg, 0);
			$this->showTrace = false;
		}
	}
?>