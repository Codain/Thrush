<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../../src/API/Nominatim.php';
	require_once __DIR__.'/../../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_NominatimTest extends TestCase
	{
		protected static function getMethod($name)
		{
			$class = new ReflectionClass('Thrush_API_Nominatim');
			$method = $class->getMethod($name);
			// $method->setAccessible(true); // Use this if you are running PHP older than 8.1.0
			
			return $method;
		}

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
		
		public function testDeduceContinent()
		{
			$deduceContinent = self::getMethod('deduceContinent');
			
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$webPageLoader = new Thrush_WebPageLoader($cache, 'nominatim', 'localhost', 'localhost');

			$nominatim = new Thrush_API_Nominatim($webPageLoader, 'user@example.com');
			
			$res = array(
				'address' => array(
					'country_code' => 'fr'
					),
				'lat' => 0,
				'lon' => 0
				);
			$this->assertEquals(
				'Europe',
				$deduceContinent->invokeArgs($nominatim, array($res))
			);
			
			$res = array(
				'address' => array(
					'country_code' => 'nz'
					),
				'lat' => 0,
				'lon' => 0
				);
			$this->assertEquals(
				'Oceania',
				$deduceContinent->invokeArgs($nominatim, array($res))
			);
			
			$res = array(
				'address' => array(
					'country_code' => 'zz'
					),
				'lat' => 0,
				'lon' => 0
				);
			$this->assertEquals(
				'Unknown',
				$deduceContinent->invokeArgs($nominatim, array($res))
			);
			
			$res = array(
				'address' => array(
					),
				'lat' => 0,
				'lon' => 0
				);
			$this->assertEquals(
				'Unknown',
				$deduceContinent->invokeArgs($nominatim, array($res))
			);
			
			$res = array(
				'address' => array(
					),
				'lat' => -89,
				'lon' => 0
				);
			$this->assertEquals(
				'Antartica',
				$deduceContinent->invokeArgs($nominatim, array($res))
			);
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