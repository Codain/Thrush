<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/Cache.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	function cacheCallback(string &$data, string $testString)
	{
		$data = $testString;
	}
	
	function testSaveExistsLoadRemoveInc(Thrush_Cache $cache, TestCase &$tc)
	{
		$type = 'CacheTest';
		$key = 'key';
		
		$cache->clear($type);
		
		// String stored as a string (no password)
		$data = 'string';
		$tc->assertEquals(
			true,
			$cache->save($type, $key, $data, '')
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data,
			$cache->load($type, $key)
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		// String stored as a string (with password)
		$data = 'string';
		$pwd = 'uuQy4CBQhRem\'H';
		$tc->assertEquals(
			true,
			$cache->save($type, $key, $data, $pwd)
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data,
			$cache->load($type, $key, $pwd)
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		// String stored as a mixed
		$data = 'string';
		$tc->assertEquals(
			true,
			$cache->saveMixed($type, $key, $data)
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data,
			$cache->loadMixed($type, $key)
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		// Integer stored as a mixed
		$data = 28;
		$tc->assertEquals(
			true,
			$cache->saveMixed($type, $key, $data)
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data,
			$cache->loadMixed($type, $key)
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		// Array stored as a mixed
		$data = array(1, 2, 3);
		$tc->assertEquals(
			true,
			$cache->saveMixed($type, $key, $data)
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data,
			$cache->loadMixed($type, $key)
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		// Object stored as a mixed
		$data = new DateTime();
		$tc->assertEquals(
			true,
			$cache->saveMixed($type, $key, $data)
		);
		$tc->assertEquals(
			true,
			$cache->exists($type, $key)
		);
		$tc->assertEquals(
			$data->getTimestamp(),
			$cache->loadMixed($type, $key)->getTimestamp()
		);
		$tc->assertEquals(
			true,
			$cache->remove($type, $key)
		);
		$tc->assertEquals(
			false,
			$cache->exists($type, $key)
		);
		
		$cache->clear($type);
	}
	
	final class Thrush_Cache_FilesTest extends TestCase
	{
		public function testDirectoryDoesNotExists1()
		{
			$this->expectException(Thrush_Exception::class);
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './doesnotexists/');
		}
		
		public function testDirectoryDoesNotExists2()
		{
			$this->expectException(Thrush_Exception::class);
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './doesnotexists');
		}
		
		public function testDirectoryExists1()
		{
			$this->assertInstanceOf(
				Thrush_Cache_Files::class,
				new Thrush_Cache_Files('localhost', 'localhost', './cache/')
			);
		}
		
		public function testDirectoryExists2()
		{
			$this->assertInstanceOf(
				Thrush_Cache_Files::class,
				new Thrush_Cache_Files('localhost', 'localhost', './cache')
			);
		}
		
		public function testEnableDisable()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			
			$cache->enable('CacheTest');
			$this->assertEquals(
				true,
				$cache->isEnabled('CacheTest')
			);
			
			$cache->disable('CacheTest');
			$this->assertEquals(
				false,
				$cache->isEnabled('CacheTest')
			);
			
			$cache->enable('CacheTest');
			$this->assertEquals(
				true,
				$cache->isEnabled('CacheTest')
			);
		}
		
		public function cacheCallback(string &$data, string $testString)
		{
			$data = $testString;
		}
		
		public function testLoadUrlCallback()
		{
			$type = 'CacheTest';
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$cache->clear($type);
			
			$cache->disable($type);
			$testString = ''; // A dummy variable to test callback
			
			// First test: Callback in global scope
			$testString = __DIR__;
			$ret = $cache->loadURLFromWebOrCache($type, 'https://http.codes/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array('cacheCallback', $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
			
			// Second test: Callback in class scope
			$testString = __FILE__;
			$ret = $cache->loadURLFromWebOrCache($type, 'https://http.codes/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'cacheCallback'), $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
			
			$cache->clear($type);
		}
		
		public function testHttpError404()
		{
			$type = 'CacheTest';
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$cache->clear($type);
			
			$cache->enable($type);
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$cache->loadURLFromWebOrCache($type, 'https://http.codes/404', null, '404', Thrush_Cache::LIFE_IMMORTAL, '', null);
			
			$cache->clear($type);
		}
		
		public function testHttpError500()
		{
			$type = 'CacheTest';
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$cache->clear($type);
			
			$cache->enable($type);
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$cache->loadURLFromWebOrCache($type, 'https://http.codes/500', null, '500', Thrush_Cache::LIFE_IMMORTAL, '', null);
			
			$cache->clear($type);
		}
		
		public function testSaveExistsLoadRemove()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			testSaveExistsLoadRemoveInc($cache, $this);
		}
	}
?>