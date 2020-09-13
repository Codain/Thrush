<?php
	declare(strict_types=1);
	
	require_once __DIR__.'/../src/Template.php';
	require_once __DIR__.'/../src/Exception.php';
	
	use PHPUnit\Framework\TestCase;

	final class Thrush_TemplateTest extends TestCase
	{
		protected function cleanOutput(string $output)
		{
			return preg_replace('!\s+!', ' ', $output);
		}
		
		public function testCreate()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
		}
		
		public function testBasicVariable()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello {WORLD}!');
			$template->setRootVariable('WORLD', 'World');
			$res = $template->render('body', false);
			
			$this->assertEquals(
				$res,
				'Hello World!'
			);
		}
		
		public function testVariableWithBasicFunction()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello {WORLD|strtoupper()}!');
			$template->setRootVariable('WORLD', 'World');
			$res = $template->render('body', false);
			
			$this->assertEquals(
				$res,
				'Hello WORLD!'
			);
		}
		
		public function testVariableWithComplexFunctions()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', '{ONE}+2={ONE|add(2)} and {TWO}+3={TWO|add(3)}');
			$template->setRootVariable('ONE', 1);
			$template->setRootVariable('TWO', 2);
			$res = $template->render('body', false);
			
			$this->assertEquals(
				$res,
				'1+2=3 and 2+3=5'
			);
		}
		
		public function testVariableWithPipelinedFunctions()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello {WORLD|strtoupper()|substr(0, 3)}!');
			$template->setRootVariable('WORLD', 'World');
			$res = $template->render('body', false);
			
			$this->assertEquals(
				$res,
				'Hello WOR!'
			);
		}
		
		public function testBlockWithBasicVariable()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello 
				<!-- BEGIN block -->
				({block.VAR1} {block.VAR2}) 
				<!-- END block -->
				!');
			
			for($i=3; $i>=0; $i--)
			{
				$template->setNewBlockVariables('block', array(
					'VAR1' => $i,
					'VAR2' => 3-$i
					));
			}
			
			$res = $template->render('body', false);
			
			$res = $this->cleanOutput($res);
			
			$this->assertEquals(
				$res,
				'Hello (3 0) (2 1) (1 2) (0 3) !'
			);
		}
		
		public function testBlockWithOrder()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello 
				<!-- BEGIN block ORDER VAR1 ASC -->
				({block.VAR1} {block.VAR2}) 
				<!-- END block -->
				!');
			
			for($i=3; $i>=0; $i--)
			{
				$template->setNewBlockVariables('block', array(
					'VAR1' => $i,
					'VAR2' => 3-$i
					));
			}
			
			$res = $template->render('body', false);
			
			$res = $this->cleanOutput($res);
			
			$this->assertEquals(
				$res,
				'Hello (0 3) (1 2) (2 1) (3 0) !'
			);
		}
	}
?>