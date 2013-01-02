<?php
if (!function_exists('apc_fetch')) { 
	function apc_fetch($k) {
		return false;
	}
	function apc_add($k,$v) {
		return false;
	}
	function apc_store($k,$v) {
		return false;
	}
}