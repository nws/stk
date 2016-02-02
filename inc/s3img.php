<?php

inc('amazon_s3');

class s3img {
	static $s3_instance = null;

	private static function ensure_init() {
		if (self::$s3_instance === null) {
			echo "loading s3 ".config::$img_s3_id. " ".config::$img_s3_secret."\n"; 
			self::$s3_instance = new amazon_s3(config::$img_s3_id, config::$img_s3_secret, config::$img_s3_bucket);
		}
	}

	static function full_path($path_part) {
		return config::$img_s3_prefix . 'img/' . $path_part;
	}

	static function gen_name($num_chars = 12) {
		$digits = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_";
		$dlen = strlen($digits);
		$name = '';
		$r = 0;
		$max_rand = mt_getrandmax();
		while (strlen($name) < $num_chars) {
			if ($r == 0) {
				$r = mt_rand(0, $max_rand);
			}
			$n = $r % $dlen;
			$r = ($r - $n) / $dlen;
			$name .= substr($digits, $n, 1);
		}
		return $name;
	}

	static function put($file, $name = null) {
		self::ensure_init();

		$img_type = exif_imagetype($file);
		
		if ($img_type != IMAGETYPE_JPEG && $img_type != IMAGETYPE_PNG) {
			return [false, "Wrong file type, it should be JPEG or PNG"];
		}
		
		$extensions = [
			IMAGETYPE_JPEG => 'jpeg',
			IMAGETYPE_PNG => 'png',
		];

		while (!$name) {
			$prov_name = self::gen_name() .'.'. $extensions[$img_type];
			$prov_object_key = self::full_path($prov_name);
			if (!self::$s3_instance->object_exists($prov_object_key)) { // check if avalivable
				$name = $prov_name;
			}
		}
		
		$object_key = self::full_path($name);
		
		$fh = fopen($file, 'r');
		$resp = self::$s3_instance->put_object($object_key, image_type_to_mime_type($img_type), $fh);
		fclose($fh);

		if ($resp->get('ObjectURL')) {
			return [true, $name];
		} else {
			return [false, 's3 upload error.'];
		}
	}

	static function get_url($name) {
		self::ensure_init();
		$object_key = self::full_path($name);
		return self::$s3_instance->get_object_url($object_key);
	}
}
