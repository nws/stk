<?php

class diag {
	static $cwd;
	static $original_memory_limit;

	static $fatal_errors = array( // if true, it is fatal
		E_ERROR => true,
		E_PARSE => true,
		E_CORE_ERROR => true,
		E_COMPILE_ERROR => true,
		E_USER_ERROR => true,
		E_RECOVERABLE_ERROR  => true,
	);

	static $errors = array(
		E_ERROR => 'E_ERROR',
		E_PARSE => 'E_PARSE',
		E_CORE_ERROR => 'E_CORE_ERROR',
		E_COMPILE_ERROR => 'E_COMPILE_ERROR',
		E_USER_ERROR => 'E_USER_ERROR',
		E_RECOVERABLE_ERROR  => 'E_RECOVERABLE_ERROR ',
		E_WARNING => 'E_WARNING',
		E_NOTICE => 'E_NOTICE',
		E_CORE_WARNING => 'E_CORE_WARNING',
		E_COMPILE_WARNING => 'E_COMPILE_WARNING',
		E_USER_WARNING => 'E_USER_WARNING',
		E_USER_NOTICE => 'E_USER_NOTICE',
		E_STRICT => 'E_STRICT',
		E_DEPRECATED => 'E_DEPRECATED',
		E_USER_DEPRECATED => 'E_USER_DEPRECATED',
	);

	static function setup() {
		if (!function_exists('xdebug_get_function_stack')) {
			return;
		}

		$memory_limit = ini_get('memory_limit');
		if (preg_match('/^\d+M$/', $memory_limit)) {
			self::$original_memory_limit = $memory_limit;
			$memory_limit = intval($memory_limit)-2;
			ini_set('memory_limit', $memory_limit.'M');
		}

		self::$cwd = rtrim(realpath(getcwd()), '/').'/';
		set_error_handler(array(__CLASS__, 'error_handler'));
		register_shutdown_function(array(__CLASS__, 'shutdown_function'));
	}

	// if we return true, we silence php's shit
	static function error_handler($nr, $str, $file, $line, $ctx) {
		if (!(error_reporting() & $nr)) { // silenced error (@ or error_reporting() settings)
			return true;
		}
		$is_fatal = !empty(self::$fatal_errors[ $nr ]);

		// log all of the error here, backtrace, whatever
		$error = array(
			'type' => $nr,
			'message' => $str,
			'file' => $file,
			'line' => $line,
		);
		self::log_error($error, $is_fatal);

		// it is now my responsibility to die if this should've been fatal.
		if ($is_fatal) {
			self::die_gracefully($error);
		}

		return true;
	}

	static function shutdown_function() {
		$error = error_get_last();
		if ($error === null) { // this will 
			return;
		}
		// this was a fatal error (or should be...)
		// log it with bt
		self::log_error($error, true);
		self::die_gracefully($error);
	}

	static function log_error($error, $is_fatal = false) {
		if (self::$original_memory_limit !== null) {
			ini_set('memory_limit', self::$original_memory_limit);
		}
		
		$error_name = isset(self::$errors[ $error['type'] ]) 
			? self::$errors[ $error['type'] ] 
			: $error['type'];

		$prefix = ($is_fatal ? 'STK FATAL ' : 'STK ERROR ').$error_name.' {'.t::current_tile().'}';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$prefix .= ' ('.$_SERVER['REQUEST_URI'].')';
		}
		else if (!empty($_SERVER['argv'])) {
			$argv = $_SERVER['argv'];
			array_shift($argv);
			$prefix .= ' ('.implode(' ', $argv).')';
		}

		$prefix .= ': ';

		stk_error_log($prefix."{$error['message']} - {$error['file']}:{$error['line']}");
		$bt = array_reverse(xdebug_get_function_stack());
		foreach ($bt as $b) {
			if ($b = self::format_stack_line($b)) {
				stk_error_log($prefix.$b);
			}
		}
	}

	static function format_stack_line($b) {
		if (!empty($b['class']) && $b['class'] == __CLASS__) { // hide ourselves
			return;
		}

		$s = '';
		if (!empty($b['class'])) {
			$s .= $b['class'];

			if (!empty($b['type'])) {
				if ($b['type'] == 'static') {
					$s .= '::';
				}
				else if ($b['type'] == 'dynamic') {
					$s .= '->';
				}
				else {
					$s .= " ?{$b['type']}? ";
				}
			}
			else {
				$s .= ' ::/-> ';
			}
		}
		if (!empty($b['function'])) {
			$s .= $b['function'];
			$params = array();
			foreach ($b['params'] as $k => $v) {
				$params[] = "\${$k} = $v";
			}
			$s .= '('.implode(', ', $params).')';
		}

		if (!empty($b['include_filename'])) {
			$s .= "INCLUDE {$b['include_filename']}";
		}

		$s .= " - {$b['file']}:{$b['line']}";

		return $s;
	}

	static function die_gracefully($error) {
		if (ob_get_length() !== false) {
			ob_end_clean();
		}
		if (php_sapi_name() == 'cli') {
			echo "FATAL: {$error['message']} at {$error['file']}:{$error['line']}\n";
		}
		else {
			if (!headers_sent()) {
				header('Content-Type: text/html; charset=UTF-8', true, 500);
			}
			if ($message = @file_get_contents(self::$cwd.'static/fatal.http')) {
				list(, $message) = explode("\r\n\r\n", $message);
				echo $message;
			}
			else {
				echo "Unhandled error, please contact the Codebase.";
			}
		}
		session_write_close();
		exit(1) /* EXIT_OK */ ;
	}
}
