#!/usr/bin/php
<?php
error_reporting(E_ALL|E_STRICT);

$path = array(
	'.',
	'inc/',
	'inc/pear',
	'lib',
	'smarty',
);

ini_set('log_errors', 1);
ini_set('session.bug_compat_warn', 'off');
ini_set('include_path', implode(':', $path));
ini_set('gd.jpeg_ignore_warning', 1);

if ($argc < 2) {
	die("cannot init stk, usage: run.php <tile> [args]\n");
}

if (!preg_match('|^tiles/(.*?)(?:\.php)?$|', $argv[1], $m)) {
	die("{$argv[1]} looks bad\n");
}

array_shift($argv); // progname
array_shift($argv); // path

$path = $m[1];
$args = &$argv;

require_once 'stk.php';

stk_bandwagon_cli('site', $path, $args);

