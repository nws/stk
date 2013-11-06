<?php

/* generic trigger handling class */
class trigger {
	static $handlers = array();

	static function setup() {

	}

	/* register a trigger callback for an event
	 * $event is "event_name/number", where the number is the priority of the callback,
	 * and is 0 of not specified */
	static function register($event, $callback) {
		if (strpos($event, '/')) {
			list($event, $prio) = explode('/', $event, 2);
			$prio = intval($prio);
		} else {
			$prio = 0;
		}

		$args = func_get_args();
		$args = array_splice($args, 2);
		
		array_unshift($args, $event);

		if (!isset(self::$handlers[$event])) {
			self::$handlers[$event] = array();
		}
		if (!isset(self::$handlers[$event][$prio])) {
			self::$handlers[$event][$prio] = array();
		}

		self::$handlers[$event][$prio][] = array($callback, $args);
	}

	/* fire $event, calling registered callbacks in order of priority */
	static function fire($event) {
		if (!isset(self::$handlers[$event])) {
			return;
		}
		ksort(self::$handlers[$event], SORT_NUMERIC);
		foreach (self::$handlers[$event] as $prio => $callbacks) {
			foreach ($callbacks as $cb) {
				list($func, $args) = $cb;
				call_user_func_array($func, $args);
			}
		}
	}
}
