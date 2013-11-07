<?php

function filter_int($int) {
	return isset($int) and is_numeric($int);
}

function filter_string_not_empty($str) {
	return isset($str) and strlen($str) > 0;
}

function filter_email($str) {
        return !!preg_match('/^[^@]+@[^.]+\..+$/', trim($str));
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
function filter_is_date($val, $formats) {
	$result = null;
	foreach ((array)$formats as $fmt) {
		if ($result = strptime((string)$val, $fmt)) {
			break;
		}
	}
	return $result
		? true
		: "$val does not match any of ".implode(', ', (array)$formats);
}

function mangle_int_between($int, $floor, $ceil) {
	input::do_htmlentities(false);
	$int = intval($int);
	$floor = intval($floor);
	$ceil = intval($ceil);

	if ($ceil < $floor) {
		error('ceiling < floor');
	}

	if ($int < $floor) {
		return $floor;
	}
	if ($int > $ceil) {
		return $ceil;
	}
	return $int;
}


function filter_check_phone_nr($val) {
	return check_phone_nr($val) ? true : 'invalid phone number';
}
