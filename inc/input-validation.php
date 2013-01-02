<?php

function filter_int($int) {
	return isset($int) and is_numeric($int);
}

function filter_string_not_empty($str) {
	return isset($str) and strlen($str) > 0;
}

function filter_preg_match($str, $pattern) {
	return !!preg_match($pattern, $str);
}

function mangle_sprintf($val, $fmt) {
	if (is_numeric($val)) {
		return sprintf($fmt, $val);
	}
}
function filter_is_array($val) {
        if (is_array($val)) {
                return true;
        }
        return 'not an array';
}
