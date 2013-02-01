<?php

/* given a path (slash separated list of words),
 * tries to find a matching tile in the configured subdirs,
 * and pass on the rest of the path as arguments
 * otherwise return a 404page with the original path as arguments */
function route($path = null) {
	_route_init();
	$path = _route_normalize_path($path);

	$pparts = explode('/', $path);
	$loc_path = array();
	while (!empty($pparts)) {
		$current_part = array_shift($pparts);
		if (!$current_part or $current_part[0] == '.' or $current_part[0] == '_') {
			// no going up the dir tree
			// or pulling out group defs through urls
			// or empty path parts
			continue;
		}
		$loc_path[] = $current_part;


		if (_route_is_page($loc_path)) {
			$loc = implode('/', $loc_path);
			return array($loc, $pparts, true);
		}
	}

	// no matching tile, maybe a /index page?
	$loc_path[] = 'index';
	if (_route_is_page($loc_path)) {
		return array(implode('/', $loc_path), array(), true);
	}

	// nope, so it's a 404
	array_pop($loc_path);

	$error_loc = config::$errordir.'e404';
	return array($error_loc, $loc_path, config::$route_error_status);
}

function _route_init() {
	global $_route_cache;
	if ($_route_cache) {
		return;
	}
	if (config::$route_cache_path) {
		$_route_cache = @include config::$route_cache_path;
	}
}

function _route_is_page($loc_path) {
	global $_route_cache;
	// a route bevan zarva a rootdirbe, de rootdir nelkuli patheket ad vissza.
	$path = 'tiles/'.implode('/', $loc_path).'.php';
	if ($_route_cache) {
		return isset($_route_cache[$path]);
	}
	return is_file_in($path, array(SITE_PATH, STK_PATH));
}

function _route_normalize_path($path = null) {
	if (!$path) {
		return config::$sitedir.'index';
	}
	$wroot = trim(config::$webroot, '/');
	if ($wroot and strpos($path, $wroot) === 0) {
		$path = substr($path, strlen($wroot));
	}
	$path = trim($path, '/');
	return config::$sitedir.implode('/', preg_split('|/+|', $path));
}

function is_file_in($path, $rootdirs) {
	foreach ($rootdirs as $r) {
		if (is_file($r.$path)) {
			return true;
		}
	}
	return false;
}
