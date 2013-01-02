<?php
set_time_limit(60);
error_reporting(E_ALL|E_STRICT);

$path = array(
	'.',
	'inc/',
	'inc/pear',
	'lib',
	'smarty',
);

ini_set('session.bug_compat_warn', 'off');
ini_set('include_path', implode(':', $path));
ini_set('gd.jpeg_ignore_warning', 1);

require_once 'stk.php';

stk_bandwagon_web(array('site'), 2);
