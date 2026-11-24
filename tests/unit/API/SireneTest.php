<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../../src/API/Sirene.php';
	require_once __DIR__.'/../../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_API_SireneTest extends TestCase
	{
		public function testValidCache()
		{
			$cache = new Thrush_Cache_Files('localhost', 'localhost', './cache/');
			$webPageLoader = new Thrush_WebPageLoader($cache, 'sirene', 'localhost', 'localhost');
			
			$this->assertInstanceOf(
				Thrush_API_Sirene::class,
				new Thrush_API_Sirene($webPageLoader, 'dummy_t0k3n')
			);
		}
	}
?>