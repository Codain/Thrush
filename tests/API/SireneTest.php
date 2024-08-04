<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/Sirene.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_SireneTest extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './../cache/');
			
			$this->assertInstanceOf(
				Thrush_API_Sirene::class,
				new Thrush_API_Sirene($cache, 'dummy_t0k3n')
			);
		}
		
		public function testGetSiren()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './../cache/');
			$sirene = new Thrush_API_Sirene($cache, '3ecdf769-c558-39e8-a185-cd45cc57c82a');
			
			$results = $sirene->querySirenById('824029516');
		}
	}
?>