<?php

t::undelegate();
t::tmpl('none');
$args = t::argv();

$mode = array_shift($args);

switch ($mode) {
	case 'scss': 
		header('content-type: text/css');
		$target = implode('/',$args);
		$src = preg_replace('/\.css$/', '.scss',$target);
		$st = 'scss '.escapeshellarg($src).' '.escapeshellarg($target);
		exec ($st);
		print file_get_contents($target);
	break;
	default:
		die('invalid mode');
}
