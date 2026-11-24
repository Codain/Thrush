<?php
	require_once __DIR__.'/../default/classe.php';
	
	class ThemeBlank extends ThemeDefault
	{
		function functionNotInDefault($var)
		{
			return $var;
		}
		
		function getThemeName()
		{
			return 'blank';
		}
	}
?>