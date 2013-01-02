<?php

/* DO NOT PUT MORE VARS IN THIS CLASS!
 * use inc/ch/ch_session.php for that.
 */
class session extends struct {
	protected $debugmode, $debugfile, $messages = array();
	static $has_data_to_write = false;

	public function has_data_to_write() {
		return self::$has_data_to_write;
	}

	public function session_was_modified() {
		self::$has_data_to_write = true;
	}

	public function &__get($k) {
		if ($k === 'has_data_to_write' or $k === 'session_was_modified') {
			trigger_error("can't touch this!");
		}
		if (!property_exists($this, $k)) {
			trigger_error("undeclared property $k accessed in ".get_class($this), E_USER_ERROR);
		}
		return $_SESSION[$k];
	}
	public function __set($k, $v) {
		if ($k === 'has_data_to_write' or $k === 'session_was_modified') {
			trigger_error("can't touch this!");
		}
		if (!property_exists($this, $k)) {
			trigger_error("undeclared property $k accessed in ".get_class($this), E_USER_ERROR);
		}

		self::$has_data_to_write = true;

		$_SESSION[$k] = $v;
	}
}

function session() {
	return struct::get('session');
}
