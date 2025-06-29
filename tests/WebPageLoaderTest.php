<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/Cache.php';
	require_once __DIR__.'/../src/WebPageLoader.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	function webPageLoaderCallback(string &$data, string $testString)
	{
		$data = $testString;
	}
	
	final class Thrush_WebPageLoaderTest extends TestCase
	{
		protected $modeCallbackCalled = false;
		
		public function webPageLoaderCallback(string &$data, string $testString)
		{
			$data = $testString;
		}
		
		public function webPageLoaderModeCallback(string $type, string $url, ?array $postData, ?string $key, int $life, ?array $additionalHeaders)
		{
			$this->modeCallbackCalled = true;
			
			return Thrush_WebPageLoader::MODE_DEFAULT;
		}
		
		public function testStatistics()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'WebPageLoaderTest';
			$cache->disable($type);
			
			$webpageloader = new Thrush_WebPageLoader($cache, 'WebPageLoaderTest', 'My Website', 'https://my.website.com');
			$webpageloader->setModeAlwaysRefresh();
			
			$statistics = $webpageloader->getStatistics();
			$this->assertEquals(
				0,
				$statistics['calls']
			);
			
			$this->assertEquals(
				'WebPageLoaderTest',
				$statistics['type']
			);
			
			try
			{
				$ret = $webpageloader->loadURL('https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', null);
			}
			catch(Throwable $e)
			{
				// Do nothing
			}
			
			try
			{
				$ret = $webpageloader->loadURL('https://httpstat.us/404', null, '404', Thrush_Cache::LIFE_IMMORTAL, '', null);
			}
			catch(Throwable $e)
			{
				// Do nothing
			}
			
			$statistics = $webpageloader->getStatistics();
			$this->assertEquals(
				2,
				$statistics['calls']
			);
		}
		
		public function testLoadUrlCallback()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'WebPageLoaderTest';
			$cache->disable($type);
			$testString = ''; // A dummy variable to test callback
			
			$webpageloader = new Thrush_WebPageLoader($cache, 'WebPageLoaderTest', 'My Website', 'https://my.website.com');
			
			// First test: Callback in global scope
			$testString = __DIR__;
			$ret = $webpageloader->loadURL('https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array('webPageLoaderCallback', $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
			
			// Second test: Callback in class scope
			$testString = __FILE__;
			$ret = $webpageloader->loadURL('https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', array(array($this, 'webPageLoaderCallback'), $testString));
			
			$this->assertEquals(
				$ret,
				$testString
			);
		}
		
		public function testModeCallback()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'WebPageLoaderTest';
			$cache->disable($type);
			
			$webpageloader = new Thrush_WebPageLoader($cache, 'WebPageLoaderTest', 'My Website', 'https://my.website.com');
			$webpageloader->setModeCallback(array($this, 'webPageLoaderModeCallback'));
			
			$this->assertEquals(
				false,
				$this->modeCallbackCalled
			);
			
			$ret = $webpageloader->loadURL('https://httpstat.us/200', null, '200', Thrush_Cache::LIFE_IMMORTAL, '', null);
			
			$this->assertEquals(
				true,
				$this->modeCallbackCalled
			);
		}
		
		public function testHttpError404()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'WebPageLoaderTest';
			$cache->enable($type);
			
			$webpageloader = new Thrush_WebPageLoader($cache, 'WebPageLoaderTest', 'My Website', 'https://my.website.com');
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$webpageloader->loadURL('https://httpstat.us/404', null, '404', Thrush_Cache::LIFE_IMMORTAL, '', null);
		}
		
		public function testHttpError500()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$type = 'WebPageLoaderTest';
			$cache->enable($type);
			
			$webpageloader = new Thrush_WebPageLoader($cache, 'WebPageLoaderTest', 'My Website', 'https://my.website.com');
			
			$this->expectException(Thrush_CurlHttpException::class);
			
			$webpageloader->loadURL('https://httpstat.us/500', null, '500', Thrush_Cache::LIFE_IMMORTAL, '', null);
		}
	}
?>