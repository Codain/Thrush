<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/Cache.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	function cacheCallback(string &$data, string $testString)
	{
		$data = $testString;
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
		
		protected function incExistsLoadSaveRemove(string $type, string $key, string $data, string $pwd)
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			
			$cache->enable($type);
			$this->assertEquals(
				true,
				$cache->isEnabled($type)
			);
			
			// At this point no dat shall exists
			$this->assertEquals(
				false,
				$cache->exists($type, $key, Thrush_Cache::LIFE_IMMORTAL)
			);
			
			// Add data to the cache
			$this->assertEquals(
				true,
				$cache->save($type, $key, $data, $pwd)
			);
			
			// At this point data shall exists
			$this->assertEquals(
				true,
				$cache->exists($type, $key, Thrush_Cache::LIFE_IMMORTAL)
			);
			
			// Check back data
			$this->assertEquals(
				$data,
				$cache->load($type, $key, $pwd)
			);
			
			// Remove data
			$this->assertEquals(
				true,
				$cache->remove($type, $key)
			);
			
			// At this point data shall not exists anymore
			$this->assertEquals(
				false,
				$cache->exists($type, $key, Thrush_Cache::LIFE_IMMORTAL)
			);
		}
		
		public function cacheCallback(string &$data, string $testString)
		{
			$data = $testString;
		}
		
		public function testExistsLoadSaveNoPassword()
		{
			$type = 'CacheTest';
			$data = 'Some dummy data';
			$key = 'key';
			$pwd = ''; // No password
			
			$this->incExistsLoadSaveRemove($type, $key, $data, $pwd);
		}
		
		public function testExistsLoadSaveWithPassword()
		{
			$type = 'CacheTest';
			$data = 'Some dummy data';
			$key = 'key';
			$pwd = 'uuQy4CBQhRem\'H';
			
			$this->incExistsLoadSaveRemove($type, $key, $data, $pwd);
		}
		
		public function testLoadUrlCallback()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'CacheTest';
			$cache->disable($type);
			$testString = ''; // A dummy variable to test callback
			
			// First test: Callback in global scope
			$testString = __DIR__;
			$ret = $cache->loadURLFromWebOrCache($type, 'https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array('cacheCallback', $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
			
			// Second test: Callback in class scope
			$testString = __FILE__;
			$ret = $cache->loadURLFromWebOrCache($type, 'https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'cacheCallback'), $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
		}
		
		public function testHttpError404()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'CacheTest';
			
			$cache->enable($type);
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$cache->loadURLFromWebOrCache($type, 'https://httpstat.us/404', null, '404', Thrush_Cache::LIFE_IMMORTAL, '', null);
		}
		
		public function testHttpError500()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'CacheTest';
			
			$cache->enable($type);
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$cache->loadURLFromWebOrCache($type, 'https://httpstat.us/500', null, '500', Thrush_Cache::LIFE_IMMORTAL, '', null);
		}
	}
?>