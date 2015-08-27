<?php

class cstore {
	static
		$cookie_name,
		$cookie_ttl,
		$cookie_secret;

	public static function setup($params) {
		self::$cookie_name = $params['cookie_name'];
		self::$cookie_ttl = $params['cookie_ttl'];
		self::$cookie_secret = $params['cookie_secret'];
	}

	public static function put($data) {
		$data = self::encode_decode(true, $data);
		setcookie(self::$cookie_name, $data, time() + self::$cookie_ttl, '/', config::$cookie_domain);
	}

	public static function get() {
		if (!empty($_COOKIE[ self::$cookie_name ])) {
			return self::encode_decode(false, $_COOKIE[ self::$cookie_name ]);
		}
		return array();
	}

	public static function delete() {
		setcookie(self::$cookie_name, '', time() - self::$cookie_ttl, '/', config::$cookie_domain);
	}

	private static function encode_decode($do_encode, $data) {
		if (config::$instance_type == 'dev') {
			return self::_encode_decode_plain($do_encode, $data);
		}
		else {
			return self::_encode_decode_crypt($do_encode, $data);
		}
	}

	private static function _encode_decode_plain($do_encode, $data) {
		if ($do_encode) {
			$default = '';
			$output = @json_encode($data);
		}
		else {
			$default = array();
			$output = @json_decode($data, true);
		}

		return $output ? $output : $default;
	}

	private static function _encode_decode_crypt($do_encode, $data) {
		$td = mcrypt_module_open('rijndael-128', '', 'ecb', '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

		$key = substr(self::$cookie_secret, 0, mcrypt_enc_get_key_size($td));

		mcrypt_generic_init($td, $key, $iv);

		if ($do_encode) {
			$default = '';
			$output = @base64_encode(mcrypt_generic($td, json_encode($data)));
		}
		else {
			$default = array();
			$output = @json_decode(mdecrypt_generic($td, base64_decode($data)), true);
		}

		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);

		return $output ? $output : $default;
	}
}
