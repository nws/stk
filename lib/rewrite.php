<?php

/*
 * return:
 * 	0: name of configfile to use
 * 	1: path to route
 * 	will call _rewrite_$sitename for each site passed to it, first one that returns true is the site loaded
 */
function rewrite($sites) {
	foreach ($sites as $s) {
		inc('/rewrite/'.$s);
		$func = '_rewrite_'.$s;
		if (!function_exists($func)) {
			continue;
		}
		$rv = $func();
		if ($rv) {
			return $rv;
		}
	}
	return array('t', ''); // absolute default
}
