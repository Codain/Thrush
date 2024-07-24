<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/LaunchLibrary2.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_LaunchLibrary2Test extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			
			$this->assertInstanceOf(
				Thrush_API_LaunchLibrary2::class,
				new Thrush_API_LaunchLibrary2($cache)
			);
		}
	}
?>