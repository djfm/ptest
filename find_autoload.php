<?php

@ini_set('display_errors', 'on');

$autoloads = [
	__DIR__.'/vendor/autoload.php',
	__DIR__.'/../../autoload.php'
];

foreach ($autoloads as $path)
{	
	if (file_exists($path))
	{
		require $path;
		break;
	}
}