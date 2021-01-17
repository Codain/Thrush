<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/SitemapUrlset.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_SitemapUrlsetTest extends TestCase
	{
		public function testAddUrl()
		{
			$sitemap = new Thrush_SitemapUrlset();
			$sitemap->addURL('https://www.example.org/', array('priority' => 0.8));
			$sitemap->addURL('https://www.example.org/folder/file.htm', array('lastmod' => '2018-11-28'));
			$sitemap->addURL('https://www.example.org/folder/file-escaped.php?var1=1&var2=2');
			
			$this->assertEquals(
				$sitemap->getXML(),
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://www.example.org/</loc><priority>0.8</priority></url><url><loc>https://www.example.org/folder/file.htm</loc><lastmod>2018-11-28</lastmod></url><url><loc>https://www.example.org/folder/file-escaped.php?var1=1&amp;var2=2</loc></url></urlset>
'
			);
		}
		
		public function testAddTooManyUrl()
		{
			$sitemap = new Thrush_SitemapUrlset();
			
			for($i=0; $i<50000; $i++)
			{
				$sitemap->addURL('file.htm');
			}
			
			$this->expectException(Thrush_Exception::class);
			
			$sitemap->addURL('file.htm');
		}
		
		public function testAddUrlWithoutProtocol()
		{
			$sitemap = new Thrush_SitemapUrlset();
			
			$this->expectException(Thrush_Exception::class);
			
			$sitemap->addURL('file.htm');
		}
		
		public function testAddUrlTooLong()
		{
			$sitemap = new Thrush_SitemapUrlset();
			
			$this->expectException(Thrush_Exception::class);
			
			$sitemap->addURL('https://111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111');
		}
	}
?>