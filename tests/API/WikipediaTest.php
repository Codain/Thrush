<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/Wikipedia.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_WikipediaTest extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache('localhost', 'localhost', './cache/');
			
			$this->assertInstanceOf(
				Thrush_Wikipedia::class,
				new Thrush_Wikipedia($cache)
			);
		}
		
		public function testInvalidCache()
		{
			$this->expectException(Thrush_Exception::class);

			new Thrush_Wikipedia(null);
		}
	}
?>