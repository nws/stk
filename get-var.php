#!/usr/bin/php5
<?php

array_shift($argv);
$key = array_shift($argv);
$fmt = '';
if (strpos($key, '/') !== false) {
	list($key, $fmt) = explode('/', $key, 2);
}
if (!$fmt) {
	$fmt = 'raw';
}

foreach ($argv as $cfg) {
	include $cfg;
}

$var = config::$$key;

switch ($fmt) {
	case 'raw':
		echo $var, "\n";
		break;
	case 'export':
		var_export($var);
		break;
	default:
		die('wtf '.$fmt);
}


