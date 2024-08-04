<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/CompaniesHouse.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_CompaniesHouseTest extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './../cache/');
			
			$this->assertInstanceOf(
				Thrush_API_CompaniesHouse::class,
				new Thrush_API_CompaniesHouse($cache, 'dummy_t0k3n')
			);
		}
		
		public function testGetCompany()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './../cache/');
			$sirene = new Thrush_API_CompaniesHouse($cache, '17a2fe93-55ac-4def-af49-1307eaa8cd75');
			
			$company = $sirene->queryCompanyById('01946167');
			
			$this->assertInstanceOf(
				Thrush_API_CompaniesHouse_Company::class,
				$company
			);
		}
	}
?>