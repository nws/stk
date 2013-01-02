<?php

class session_mysql {
	static $m;
	static function setup($sid=false) {
		static $done = false;
		if (!$sid && $done) {
			return;
		}
		session_set_save_handler(
			array(__CLASS__, 'open'),
			array(__CLASS__, 'close'),
			array(__CLASS__, 'read'),
			array(__CLASS__, 'write'),
			array(__CLASS__, 'destroy'),
			array(__CLASS__, 'gc')
		);
		session_name(config::$session_cookie_name);
		ini_set('session.cookie_domain', config::$cookie_domain);
		if ($sid) {
			session_id($sid);
		}
		session_start();
		if ($sid && $done) {
			// because we cannot header unset things we have to get rid of the already set session cookie
			foreach (array_reverse(headers_list()) as $h) {
				if (stripos($h,'Set-Cookie: '.config::$session_cookie_name.'=')===0) {
					header($h,true);
					break;
				}
			}
		}
		$done = true;
	}

	static function open($save_path, $session_name) {
		self::$m = models::get('session_store');
		return true;
	}
	
	static function close() {
		return true;
	}

	static function read($id) {
		if (empty($_COOKIE[session_name()])) {
			return '';
		}
		return self::$m->read(self::strip_frontend_pfx($id));
	}

	static function write($id, $sess_data) {
		if (session()->has_data_to_write()) {
			return self::$m->write(self::strip_frontend_pfx($id), $sess_data);
		}
		return true;
	}

	static function destroy($id) {
		return self::$m->destroy(self::strip_frontend_pfx($id));
	}

	static function gc($maxlifetime) {
		return self::$m->gc();
	}

	static function strip_frontend_pfx($id) {
		if (strlen($id) > 32) {
			$id = substr($id, -32);
		}
		return $id;
	}
}
