<?php
	/*--------------------------------------------------------------------
					  The PHP License, version 2.02
	Copyright (c) 1999 - 2002 The PHP Group. All rights reserved.
	--------------------------------------------------------------------

	Redistribution and use in source and binary forms, with or without
	modification, is permitted provided that the following conditions
	are met:

	  1. Redistributions of source code must retain the above copyright
		 notice, this list of conditions and the following disclaimer.

	  2. Redistributions in binary form must reproduce the above
		 copyright notice, this list of conditions and the following
		 disclaimer in the documentation and/or other materials provided
		 with the distribution.

	  3. The name "PHP" must not be used to endorse or promote products
		 derived from this software without prior permission from the
		 PHP Group.  This does not apply to add-on libraries or tools
		 that work in conjunction with PHP.  In such a case the PHP
		 name may be used to indicate that the product supports PHP.

	  4. The PHP Group may publish revised and/or new versions of the
		 license from time to time. Each version will be given a
		 distinguishing version number.
		 Once covered code has been published under a particular version
		 of the license, you may always continue to use it under the
		 terms of that version. You may also choose to use such covered
		 code under the terms of any subsequent version of the license
		 published by the PHP Group. No one other than the PHP Group has
		 the right to modify the terms applicable to covered code created
		 under this License.

	  5. Redistributions of any form whatsoever must retain the following
		 acknowledgment:
		 "This product includes PHP, freely available from
		 http://www.php.net/".

	  6. The software incorporates the Zend Engine, a product of Zend
		 Technologies, Ltd. ("Zend"). The Zend Engine is licensed to the
		 PHP Association (pursuant to a grant from Zend that can be
		 found at http://www.php.net/license/ZendGrant/) for
		 distribution to you under this license agreement, only as a
		 part of PHP.  In the event that you separate the Zend Engine
		 (or any portion thereof) from the rest of the software, or
		 modify the Zend Engine, or any portion thereof, your use of the
		 separated or modified Zend Engine software shall not be governed
		 by this license, and instead shall be governed by the license
		 set forth at http://www.zend.com/license/ZendLicense/.

	THIS SOFTWARE IS PROVIDED BY THE PHP DEVELOPMENT TEAM ``AS IS'' AND
	ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
	THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
	PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE PHP
	DEVELOPMENT TEAM OR ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
	INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
	SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.

	--------------------------------------------------------------------

	This software consists of voluntary contributions made by many
	individuals on behalf of the PHP Group.

	The PHP Group can be contacted via Email at group@php.net.

	For more information on the PHP Group and the PHP project,
	please see <http://www.php.net>.*/


/**
* RC4 stream cipher routines implementation.
* Inspired from PEAR package (author Dave Mertens <dmertens@zyprexia.com>)
*/
class Thrush_CryptRC4
{
	/**
	* Internal values
	*/
	protected $s = array();
	protected $i = 0;
	protected $j = 0;

	/**
	* string Encryption Key
	*/
	protected $key = null;

	/**
	* Constructor
	*
	* \param string key
	*   Key which will be used for encryption (optional)
	*/
	function __construct(string $key=null)
	{
		if(!is_null($key))
		{
			$this->setKey($key);
		}
	}

	function setKey(string $key)
	{
		if(is_null($key))
		{
			throw new Thrush_Exception('Error', 'Encryption key shall not be null');
		}
		
		if(strlen($key) === 0)
		{
			throw new Thrush_Exception('Error', 'Encryption key shall not be empty');
		}
		
		$this->key = $key;
	}

	/**
	* Initialise encryption data
	*/
	protected function initEncryption()
	{
		if(is_null($key))
		{
			throw new Thrush_Exception('Error', 'Encryption key shall not be null');
		}
		
		$len = strlen($this->key);
		for($this->i = 0; $this->i < 256; $this->i++)
		{
			$this->s[$this->i] = $this->i;
		}
		
		$this->j = 0;
		for($this->i = 0; $this->i < 256; $this->i++)
		{
			$this->j = ($this->j + $this->s[$this->i] + ord($this->key[$this->i % $len])) % 256;
			$t = $this->s[$this->i];
			$this->s[$this->i] = $this->s[$this->j];
			$this->s[$this->j] = $t;
		}
		
		$this->i = $this->j = 0;
	}

	/**
	* Encrypt function
	*
	* \param string paramstr
	*   String that will encrypted
	*/
	public function crypt(string &$paramstr)
	{
		// Init key for every call
		$this->initEncryption();
		
		$len = strlen($paramstr);
		for($c= 0; $c < $len; $c++)
		{
			$this->i = ($this->i + 1) % 256;
			$this->j = ($this->j + $this->s[$this->i]) % 256;
			$t = $this->s[$this->i];
			$this->s[$this->i] = $this->s[$this->j];
			$this->s[$this->j] = $t;
			
			$t = ($this->s[$this->i] + $this->s[$this->j]) % 256;
			
			$paramstr[$c] = chr(ord($paramstr[$c]) ^ $this->s[$t]);
		}
	}

	/**
	* Decrypt function
	*
	* \param string paramstr
	*   String that will decrypted
	*/
	public function decrypt(string &$paramstr)
	{
		//Decrypt is exactly the same as encrypting the string. Reuse (en)crypt code
		$this->crypt($paramstr);
	}
}
?>
