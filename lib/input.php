<?php

class input {
	const rule_filter = 1;
	const rule_mangle = 2;

	// get the value, or a '' if not set
	const always = true; // for backwards compat
	// get value if set, otherwise skip
	const if_set = 128;

	static $input_var = null;

	static $do_htmlentities = false;

	static $global_do_htmlentities = false;

	static $current_row = null;

	static function set_escaping($val = true) {
		self::$global_do_htmlentities = $val;
	}

	static function setup() {
		if (IS_GET) {
			self::$input_var = &$_GET;
		} else if (IS_POST) {
			self::$input_var = &$_POST;
		} else {
			self::$input_var = &$_REQUEST;
		}
	}

	static function do_htmlentities($do) {
		self::$do_htmlentities = $do;
	}

	static function filter($func) {
		if (!function_exists('filter_'.$func)) {
			error("input::filter($func): function does not exist");
		}
		$args = func_get_args();
		array_shift($args);
		return array(input::rule_filter, 'filter_'.$func, $args);
	}

	static function mangle($func) {
		if (!function_exists('mangle_'.$func)) {
			error("input::mangle($func): function does not exist");
		}	
		$args = func_get_args();
		array_shift($args);
		return array(input::rule_mangle, 'mangle_'.$func, $args);
	}

	static function check_var($var) {
		$rule = func_get_args();
		array_shift($rule);

		$has = true;

		if (empty($rule)) {
			$var = html_escape($var);
		} else {
			self::$do_htmlentities = true;
			foreach ($rule as $r) {
				list($type, $func, $args) = $r;
				array_unshift($args, $var);
				$rv = call_user_func_array($func, $args);
				if ($type === self::rule_filter) {
					if ($rv !== true) {
						$has = $rv;
					}
				} else if ($type === self::rule_mangle) {
					$var = $rv;
				} else {
					error("input::check(), passed unknown type $type for field $field");
				}
			}
			if (self::$do_htmlentities) {
				$var = html_escape($var);
			} 
		}

		return array($var, $has);
	}

	static function row() {
		return self::$current_row;
	}

	static function check_result(&$array, $fields) {
		$result = array();
		foreach ($array as $row) {
			self::$current_row = &$row;
			$val = array();
			foreach ($fields as $field => $rule) {
				self::$do_htmlentities = true;
				if ($rule === self::always) {
					$val[$field] = html_escape(@$row[$field]);
				} else if ($rule === self::if_set) {
					if (isset($row[$field])) {
						$val[$field] = html_escape($row[$field]);
					}
				} else {
					if (!is_array($rule[0])) {
						// only one rule given, convert
						$rule = array($rule);
					}

					$v = @$row[$field];

					foreach ($rule as $r) {
						list($type, $func, $args) = $r;
						array_unshift($args, $v);
						$rv = call_user_func_array($func, $args);
						if ($type === self::rule_filter) {
							error("input::check_result(): filter rules are not appropriate here");
						} else if ($type === self::rule_mangle) {
							$v = $rv;
						} else {
							error("input::check_result(): passed unknown type $type for field $field");
						}
					}
					if (self::$do_htmlentities) {
						$v = html_escape($v);
					} 
					$val[$field] = $v;
				}
			}
			$result[] = $val;
		}
		self::$current_row = null;
		return $result;
	}

	static function check($fields, $var = null) {
		if ($var !== null) {
			self::$input_var = $var;
		}
		self::$do_htmlentities = false;
		$val = $has = array();
		foreach ($fields as $field => $rule) {
			if (self::$global_do_htmlentities) {
				self::$do_htmlentities = true;
			}
			if ($rule === self::always) {
				$_v = (string)@self::$input_var[$field];
				if (self::$do_htmlentities) {
					$_v = html_escape($_v);
				}
				$val[$field] = $_v;
			} else if ($rule === self::if_set) {
				if (isset(self::$input_var[$field])) {
					$_v = self::$input_var[$field];
					if (self::$do_htmlentities) {
						$_v = html_escape($_v);
					}
					$val[$field] = $_v;
				}
			} else if (is_object($rule) && is_callable($rule)) {
				$v = @self::$input_var[$field];
				$rv = $rule($v);
				if ($rv === true) {
					$val[$field] = $v;
				}
				else {
					$has[$field] = $rv;
				}
			} else {
				if (is_callable($rule) || !is_array($rule[0])) {
					// only one rule given, convert
					$rule = array($rule);
				}

				$v = @self::$input_var[$field];
				$add_field = true;
				
				foreach ($rule as $r) {
					$is_callable = is_callable($r);
					if ($is_callable) {
						$func = $r;
						$bad = null;
						$args = array(&$bad);
					}
					else {
						list($type, $func, $args) = $r;
					}
					array_unshift($args, $v);
					$rv = call_user_func_array($func, $args);
					if ($is_callable) {
						if ($bad === true) {
							$has[$field] = $rv;
						}
						else {
							$v = $rv;
						}
					}
					else if ($type === self::rule_filter) {
						if ($rv !== true) {
							$has[$field] = $rv;
							$add_field = false;
						}
					} 
					else if ($type === self::rule_mangle) {
						$v = $rv;
					}
				   	else {
						error("input::check(), passed unknown type $type for field $field");
					}
				}
				if (self::$do_htmlentities) {
					$v = html_escape($v);
				} 
				if ($add_field) {
					$val[$field] = $v;
				}
			}
		}
		if ($var !== null) {
			self::setup(); // restore self::$input_var
		}
		return array($val, (empty($has) ? true : $has));
	}

}
