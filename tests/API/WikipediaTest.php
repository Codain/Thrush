<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/Wikipedia.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_WikipediaTest extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			
			$this->assertInstanceOf(
				Thrush_API_Wikipedia::class,
				new Thrush_API_Wikipedia($cache)
			);
		}
		
		public function testParseArticleUrl()
		{
			$results = Thrush_API_Wikipedia::parseArticleUrl('https://en.wikipedia.org/wiki/The_Beatles');
			
			$this->assertEquals(
				'https',
				$results['scheme']
			);
			$this->assertEquals(
				'en',
				$results['language']
			);
			$this->assertEquals(
				'en.wikipedia.org',
				$results['host']
			);
			$this->assertEquals(
				'The_Beatles',
				$results['title']
			);
		}
	}
?>