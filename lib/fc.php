<?php

class fc {
	static function get($store, $key) {
		if (!config::$fcache_dir) {
			return;
		}
		if ($content = @file_get_contents(self::filename($store, $key))) {
			if ($decoded = @unserialize($content)) {
				return $decoded['value'];
			}
		}
	}

	static function put($store, $key, $value) {
		if (!config::$fcache_dir) {
			return;
		}
		$fn = self::filename($store, $key);
		$wrap = array(
			'value' => $value,
			'time' => time(),
		);
		file_put_contents($fn, serialize($wrap));
	}

	static function filename($store, $key) {
		$fn = config::$fcache_dir.$store.'/'.md5(json_encode($key)).'.s';

		$dir = dirname($fn);
		if (!is_dir($dir)) {
			if (!is_dir(config::$fcache_dir)) {
				@mkdir(config::$fcache_dir);
				@chmod(config::$fcache_dir, 0777);
			}
			@mkdir($dir);
			@chmod($dir, 0777);
		}

		return $fn;
	}
}
