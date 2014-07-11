<?php

t::undelegate();
t::tmpl('none');
$args = t::argv();

$mode = array_shift($args);

switch ($mode) {
	case 'scss':
		header('Content-Type: text/css');
		$target = implode('/', $args);
		$src = preg_replace('/\.css$/', '.scss', $target);
		$st = 'scss -C '.escapeshellarg($src).' '.escapeshellarg($target);
		exec($st, $output, $rv);
		if ($rv !== 0) {
			debug("SCSS compiler died with $output ($rv)");
		}
		else {
			echo file_get_contents($target);
		}
	break;
	default:
		error('invalid mode: '.$mode);
}
