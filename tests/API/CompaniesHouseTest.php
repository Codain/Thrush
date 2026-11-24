<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/CompaniesHouse.php';
	require_once __DIR__.'/../../src/WebPageLoader.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_CompaniesHouseTest extends TestCase
	{
		public function testCreateObject()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$webPageLoader = new Thrush_WebPageLoader($cache, 'companies-house', 'localhost', 'localhost');
			
			$this->assertInstanceOf(
				Thrush_API_CompaniesHouse::class,
				new Thrush_API_CompaniesHouse($webPageLoader, 'dummy_t0k3n')
			);
		}
	}
?>