<?php

class userlib {
	const salt_len = 16;
	static function encrypt_pass($pass, $salt = null) {
		if ($salt === null) {
			$salt = self::gen_salt();
		}
		$hash = sha1($salt.$pass);
		return $salt.$hash;
	}
	
	static function check_pass($pass, $hash) {
		if (!isset($hash)) {
			return null;
		}
		$salt = substr($hash, 0, self::salt_len);
		return $salt.sha1($salt.$pass) === $hash;
	}

	static function gen_salt() {
		return substr(sha1(uniqid(rand())), 0, self::salt_len);
	}

	static function pwgen() {
		$chars = "abcdefghijkmnopqrstuvwxyz";
		srand((double)microtime()*1000000);
		$i = 0;
		$pass = '' ;

		while ($i <= 4) {
			$num = rand() % strlen($chars)-1;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}

		return $pass;
	}

	static function core_data($user_id) {
		return m('user')->get_by_user_id($user_id);
	}

	static function bring_up() {
		if (session()->user_id > 0) {
			stash()->user_id = session()->user_id;
			stash()->user = self::core_data(stash()->user_id);
			stkoauth::init(stash()->user_id);
			self::init_roles();
		}
	}

	static function login($user_id, $persistent = true, $disable_orig_target=false) {
		$core = self::core_data($user_id);
		if (empty($core)) { // XXX not ideal...
			set_message('Cannot log in your user, it is broken');
			return;
		}
		stash()->user_id = $user_id;
		session()->user_id = $user_id;
		stash()->user = $core;
		stkoauth::init($user_id);
		self::init_roles();

		if ($persistent) {
			setcookie(session_name(), session_id(), time()+config::$persistent_session_lifetime, '/', config::$cookie_domain);
			m('session_store')->set_to_persistent(session_id());
		}
	}

	static function logout() {
		stash()->user = null;
		stash()->user_id = -1;
		session()->user_id = -1;
		self::remove_roles();
		setcookie(session_name(), '', time()-86400, '/', config::$cookie_domain); // remove the session cookie for sure
		session_destroy();
	}

	// if called without args, returns true if any user is logged in
	// if called with a userid, returns true if that user is logged in
	static function is_logged_in($user_id = null) {
		$suid = stash()->user_id;
		return $suid > 0 and ($user_id === null or $user_id == $suid);
	}

	static function init_roles() {
		$u = stash()->user;

		if (empty($u)) {
			self::logout();
			return;
		}

		$r = stash()->roles;
		$r->push();
		$role = !empty($u['role']) ? $u['role'] : 'user';
		$r->set($role);
	}

	static function remove_roles() {
		$r = stash()->roles;
		$r->pop();
	}
}
