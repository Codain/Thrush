<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/Nominatim.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_NominatimTest extends TestCase
	{
		public function testCanBeCreatedFromValidEmailAddress()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$webPageLoader = new Thrush_WebPageLoader($cache, 'nominatim', 'localhost', 'localhost');
			
			$this->assertInstanceOf(
				Thrush_API_Nominatim::class,
				new Thrush_API_Nominatim($webPageLoader, 'user@example.com')
			);
		}
		
		public function testCannotBeCreatedFromInvalidEmailAddress()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$webPageLoader = new Thrush_WebPageLoader($cache, 'nominatim', 'localhost', 'localhost');
			
			$this->expectException(Thrush_Exception::class);

			new Thrush_API_Nominatim($webPageLoader, 'invalid');
		}
		
		/*public function testCanBeUsedAsString(): void
		{
			$this->assertEquals(
				'user@example.com',
				Email::fromString('user@example.com')
			);
		}*/
	}
?>