<?php

class fc {
	static $force_regen = false;

	static function force_regen($v) {
		self::$force_regen = $v;
	}

	static function get($store, $key, $ttl = null) {
		if (!config::$fcache_dir) {
			return;
		}
		if (self::$force_regen) {
			return;
		}
		if ($content = @file_get_contents(self::filename($store, $key))) {
			if ($decoded = @unserialize($content)) {
				if ($ttl !== null) {
					$age = time() - $decoded['time'];
					if ($age > $ttl) {
						return;
					}
				}
				return $decoded['value'];
			}
		}
	}

	static function put($store, $key, $value) {
		if (!config::$fcache_dir) {
			return;
		}
		$fn = self::filename($store, $key);
		$dir = dirname($fn);
		if (!is_dir($dir)) {
			if (!is_dir(config::$fcache_dir)) {
				@mkdir(config::$fcache_dir);
				@chmod(config::$fcache_dir, 0777);
			}
			@mkdir($dir);
			@chmod($dir, 0777);
		}
		$wrap = array(
			'value' => $value,
			'time' => time(),
		);
		$tempfn = $fn.'.temp';
		$r = @file_put_contents($tempfn, serialize($wrap));
		if ($r===false) {
			debug('fc::put unable to write '.$fn);
		}
		else {
			@rename($tempfn, $fn);
		}
		return $r;
	}

	static function delete($store, $key) {
		if (!config::$fcache_dir) {
			return;
		}
		$fn = self::filename($store, $key);
		@unlink($fn);
	}

	static function filename($store, $key) {
		return config::$fcache_dir.$store.'/'.md5(json_encode($key)).'.s';
	}
}
