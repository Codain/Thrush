<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/Cache.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_CacheTest extends TestCase
	{
		public function testDirectoryDoesNotExists()
		{
			$this->expectException(Thrush_Exception::class);
			
			$cache = new Thrush_Cache('localhost', 'localhost', './doesnotexists/');
		}
		
		public function testEnableDisable()
		{
			$cache = new Thrush_Cache('localhost', 'localhost', './cache/');
			
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
			$cache = new Thrush_Cache('localhost', 'localhost', './cache/');
			
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
	}
?>