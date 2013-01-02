<?php

function _rewrite_site() {
	$u = strval(substr($_SERVER['REQUEST_URI'], 1));
	$u = preg_replace('!\?.*!', '', $u);
	return array('site', $u);
}

