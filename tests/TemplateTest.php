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
			
			$ct = $template->getCurrentTheme();
			$this->assertEquals(
				$ct,
				'default'
			);
		}
		
		public function testCurrentTheme()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$ct = $template->getCurrentTheme();
			$this->assertEquals(
				$ct,
				'default'
			);
			
			$template->pushCurrentTheme('blank');
			$ct = $template->getCurrentTheme();
			$this->assertEquals(
				$ct,
				'blank'
			);
			
			$template->popCurrentTheme();
			$ct = $template->getCurrentTheme();
			$this->assertEquals(
				$ct,
				'default'
			);
			
			$this->expectException(Thrush_Exception::class);
			
			$template->popCurrentTheme();
			$ct = $template->getCurrentTheme();
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
			
			$template->setContentForHandle('body', '{ONE}+2={ONE|add(2)} and {TWO}+3={TWO|add(3)} (theme={VOID|getThemeName()})');
			$template->setRootVariable('ONE', 1);
			$template->setRootVariable('TWO', 2);
			$res = $template->render('body', false);
			
			$this->assertEquals(
				$res,
				'1+2=3 and 2+3=5 (theme=default)'
			);
		}
		
		public function testVariableWithComplexFunctionsAndTheme()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->pushCurrentTheme('blank');
			$template->setContentForHandle('body', '{ONE}+{TWO|functionNotInDefault()}={ONE|add(2)} and {TWO}+3={TWO|add(3)} (theme={VOID|getThemeName()})');
			$template->setRootVariable('ONE', 1);
			$template->setRootVariable('TWO', 2);
			$template->popCurrentTheme();
			$res = $template->render('body', false);
			
			// Method in theme 'blank' shall be called whenever possible
			
			$this->assertEquals(
				$res,
				'1+2=3 and 2+3=5 (theme=blank)'
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
		
		public function testBlockWithBasicVariableInline()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello <!-- BEGIN block -->({block.VAR1} {block.VAR2}) <!-- END block -->!');
			
			for($i=3; $i>=0; $i--)
			{
				$template->setNewBlockVariables('block', array(
					'VAR1' => $i,
					'VAR2' => 3-$i
					));
			}
			
			$res = $template->render('body', false);
			
			//$res = $this->cleanOutput($res);
			
			$this->assertEquals(
				$res,
				'Hello (3 0) (2 1) (1 2) (0 3) !'
			);
		}
		
		public function testBlockWithOrderAsc()
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
		
		public function testBlockWithOrderDesc()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$template->setContentForHandle('body', 'Hello 
				<!-- BEGIN block ORDER VAR2 DESC -->
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
		
		public function testBlockWithOrderFailure1()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$this->expectException(Thrush_Exception::class);
			
			// 'IDONTKNOW' is not a valid ordering criteria
			$template->setContentForHandle('body', 'Hello 
				<!-- BEGIN block ORDER VAR2 IDONTKNOW -->
				({block.VAR1} {block.VAR2}) 
				<!-- END block -->
				!');
		}
		
		public function testBlockWithOrderFailure2()
		{
			$template = new Thrush_Template(__DIR__.'/ressources/templates');
			
			$this->expectException(Thrush_Exception::class);
			
			// 'var' is not a valid variable
			$template->setContentForHandle('body', 'Hello 
				<!-- BEGIN block ORDER var ASC -->
				({block.VAR1} {block.VAR2}) 
				<!-- END block -->
				!');
		}
	}
?>