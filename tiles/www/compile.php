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
		$st = 'scss -C '.escapeshellarg($src).' '.escapeshellarg($target).' 2>&1';
		exec($st, $output, $rv);
		if ($rv !== 0) {
			debug("SCSS compiler died with ".print_r($output,1)." ($rv)");
			die("SCSS compiler died with ".print_r($output,1)." ($rv)");
		}
		else {
			$args[0].='_00'.time().rand(0,1000);
			$target = implode('/', $args);
			redirect($target);
		}
	break;
	default:
		error('invalid mode: '.$mode);
}
