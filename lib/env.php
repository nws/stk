<?php

class env {
	static function setup() {
		$functions = get_class_methods('env');
		foreach ($functions as $f) {
			if ($f == 'setup') {
				continue;
			}
			self::$f();
		}
	}

	static function rq_method() {
		if (@$_SERVER['REQUEST_METHOD'] == 'POST') {
			define('IS_POST', true);
			define('IS_GET', false);
		} else {
			define('IS_POST', false);
			define('IS_GET', true);
		}
	}
}
