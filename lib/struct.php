<?php

/* generic struct class in php.
 * extend this, and declare protected members.
 * if anyone tries to access undeclared members, the class dies noisily */
class struct {
	private static $struct;
	static function load($type, $cl) {
		if (!isset(self::$struct)) {
			self::$struct = array();
		}
		if (!isset(self::$struct[$type])) {
			self::$struct[$type] = new $cl();
		}
	}
	static function is_loaded($type) {
		return isset(self::$struct);
	}
	static function get($type) {
		return self::$struct[$type];
	}
	public function &__get($k) {
		if (!property_exists($this, $k)) {
			trigger_error("undeclared property $k accessed in ".get_class($this), E_USER_ERROR);
		}
		return $this->$k;
	}
	public function __set($k, $v) {
		if (!property_exists($this, $k)) {
			trigger_error("undeclared property $k accessed in ".get_class($this), E_USER_ERROR);
		}

		$this->$k = $v;
	}
}

