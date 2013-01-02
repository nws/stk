<?php

/*
 * store_method
 * image_type
 * size 
 *
 * dir layout:
 * 	files/
 * 		$type/
 * 			$size/
 * 				$hash_prefix/
 * 					$hashed_filename
 *
 * dbvalue: type:store_method:rest
 * {...|imageurl:size_key} -> rest can be deduced from the dbvalue
 * 		
 * store_img(field, image_type, store_method) ->
 * 		foreach (defined_sizes_for(image_type)) {
 * 			resize&store(field)
 * 		}
 *
 * new store_methods need:
 * _store_img_$store_method($type, $file_struct, $config)
 * _get_url_$store_method($key, $type, $size, $config)
 * _remove_img_$store_method($key, $type, $config)
 * _get_file_$store_method($key, $type, $size, $config)
 */

class img {
	static function setup() {
	}

	static function get_info($file_key) {
		if (!$file_key) {
			return null;
		}
		if (strpos($file_key, ':') === false) {
			debug("img::get_info($file_key): no store method in this key");
			return null;
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);

		$hlpm = '_get_url_'.$store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::get_info($file_key): no such store method");
			return null;
		}

		return array(
			'type' => $type,
			'store_method' => $store_method,
			'key' => $key,
		);
	}

	static function has_img($field) {
		return isset($_FILES[$field]) 
			and $_FILES[$field]['error'] === UPLOAD_ERR_OK 
			and is_uploaded_file($_FILES[$field]['tmp_name']);
	}

	static function data($field) {
		return $_FILES[$field];
	}

	static function store_img_internal($type, $store_method, $file_struct, $size = null) {
		$call_store_method = $store_method;
		if (config::$use_internal_image_api 
			&& in_array($store_method, array('temp', 'local'))) 
		{
			$call_store_method = config::$use_internal_image_api;
		}

		$hlpm = '_store_img_'.$call_store_method;	
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::store_img_internal($type, $call_store_method, ...): no such store method");
		}

		$key = $type.':'.$store_method.':'.call_user_func(
			array(__CLASS__, $hlpm),
			$type,
			$file_struct,
			config::$img_stores[$store_method],
			$store_method,
			$size
		);
		// available sizes of 'local', since that's what we pull stuff out by anyway
		return $key;
	}

	static function store_img_url($url, $type, $store_method) {
		$tempfile = tempnam('/tmp/', 'imgstore-url-image-');
		$tempfh = fopen($tempfile, 'w');
		if (!$tempfh) {
			debug('cannot open '.$tempfile);
			unlink($tempfile);
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_FILE, $tempfh);
		$rv = curl_exec($ch);
		if (!$rv) {
			debug('curl failed for '.$url.': '.curl_error($ch));
			return false;
		}
		fclose($tempfh);
		$r = getimagesize($tempfile);
		if (!$r) {
			debug('not valid image');
			return false;
		}
		$ext = img::get_ext_from_mime($r['mime']);
		
		$newtemp = $tempfile. '.'.$ext;
		rename($tempfile, $newtemp);

		$file_struct = array('name' => $newtemp, 'tmp_name' => $newtemp);
		return self::store_img_internal($type, $store_method, $file_struct);
	}
	
	static function store_img_raw($data, $type, $store_method, $size = null, $original_filename = null) {
		if ($data === '') {
			return false;
		}
		$tempfile = tempnam('/tmp/', 'imgstore-url-image-');
		$tempfh = fopen($tempfile, 'w');
		if (!$tempfh) {
			debug('cannot open '.$tempfile);
			unlink($tempfile);
			return false;
		}
		if(fwrite($tempfh, $data) === false) {
			debug('write failed on '.$tempfile);
			return false;
		}
		fclose($tempfh);

		$imgdata = getimagesize($tempfile);

		if (!in_array($imgdata[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF))) {
			@unlink($tempfile);
			return false;
		}

		list($mime, $ext) = explode('/', $imgdata['mime']);
		if ($ext == 'jpeg') {
			$ext = 'jpg';
		}
		if ($mime != 'image') {
			unlink($tempfile);
			return false;
		}
		$name = "$tempfile.$ext";
		if ($original_filename !== null) {
			$name = $original_filename;
		}
		$file_struct = array('name' => $name, 'tmp_name' => $tempfile);
		return self::store_img_internal($type, $store_method, $file_struct, $size);
	}

	// $path is either the real filename OR an array with (filename at upload time, real local filename)
	static function store_img_file($path, $type, $store_method, $size = null) {
		if (is_array($path)) {
			$file_struct = array('name' => $path[0], 'tmp_name' => $path[1]);
		} else {
			$file_struct = array('name' => $path, 'tmp_name' => $path);
		}
		return self::store_img_internal($type, $store_method, $file_struct, $size);
	}

	static function store_img_upload($field, $type, $store_method, $size = null) {
		if (!self::has_img($field)) {
			error("img::store_img($field, $store_method): no such uploaded file");
		}

		return self::store_img_internal($type, $store_method, $_FILES[$field], $size);
	}

	static function restore_img($key, $type, $size, $store_method) {
		$bytes = self::get_bytes($key, 'original');
		if (empty($bytes) && $type == 'photo') {
			$bytes = self::get_bytes($key, 'large');
		}
		$realname = explode(':', $key);
		$realname = array_pop($realname);
		return self::store_img_raw($bytes, $type, $store_method, $size, $realname);
	}

	static function get_url($file_key, $size = 'thumb') {
		if (!$file_key) {
			return '';
		}
		if (strpos($file_key, ':') === false) {
			debug("img::get_url($file_key): no store method in this key");
			return '';
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);
		if (!$key) {
			return self::missing($type, $size);
		}
		$hlpm = '_get_url_'.$store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::get_url($file_key): no such store method");
		}

		return call_user_func(
			array(__CLASS__, $hlpm),
			$key,
			$type,
			$size,
			config::$img_stores[$store_method]
		);
	}

	static function get_all_files($file_key) {
		if (!$file_key) {
			return array();
		}
		if (strpos($file_key, ':') === false) {
			debug("img::get_all_files($file_key): no store method in this key");
			return '';
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);
		if ($store_method != 'local') {
			return array();
		}

		$sizes = array_keys(config::$img_stores[$store_method]['sizes'][$type]);
		array_unshift($sizes, 'original');

		$paths = array();
		foreach ($sizes as $s) {
			$paths[] = img::get_file($file_key, $s);
		}
		return $paths;
	}

	static function get_bytes($file_key, $size = 'thumb', $fetch_meta = false) {
		if (!$file_key) {
			return array();
		}
		if (strpos($file_key, ':') === false) {
			debug("img::get_bytes($file_key): no store method in this key");
			return array();
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);

		$call_store_method = $store_method;
		if (config::$use_internal_image_api) {
			$call_store_method = config::$use_internal_image_api;
		}

		$hlpm = '_get_bytes_'.$call_store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::get_bytes($file_key): no such store method");
		}

		$ret = false;
		if ($key) {
			$ret = call_user_func(
				array(__CLASS__, $hlpm),
				$key,
				$type,
				$size,
				$fetch_meta,
				config::$img_stores[$store_method],
				$store_method
			);
		}

		if (!$ret) {
			$missing_path = 'static/img/missing/'.$type.'_'.$size.'.jpg';
			$imgdata = @getimagesize($missing_path);
			$meta = array(
				'size' => @filesize($missing_path),
				'mime' => @$imgdata['mime'],
				'w' => @$imgdata[0],
				'h' => @$imgdata[1],
			);
			$ret = array(
				'bytes' => @file_get_contents($missing_path),
				'meta' => $meta,
			);
		}

		if ($fetch_meta) {
			return $ret;
		}
		return $ret['bytes'];
	}

	static function get_file($file_key, $size = 'thumb') {
		if (!$file_key) {
			return '';
		}
		if (strpos($file_key, ':') === false) {
			debug("img::get_file($file_key): no store method in this key");
			return '';
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);

		$hlpm = '_get_file_'.$store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::get_file($file_key): no such store method");
		}

		return call_user_func(
			array(__CLASS__, $hlpm),
			$key,
			$type,
			$size,
			config::$img_stores[$store_method]
		);
	}

	static function remove_img($file_key) {
		if (strpos($file_key, ':') === false) {
			debug("img::remove_img($file_key): no store method in this key");
			return false;
		}

		list($type, $store_method, $key) = explode(':', $file_key, 3);

		$hlpm = '_remove_img_'.$store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::remove_img($file_key): no such store method");
		}

		return call_user_func(
			array(__CLASS__, $hlpm),
			$key,
			$type,
			config::$img_stores[$store_method]
		);
	}

	static function get_ext($name) {
		$parts = explode('.', $name);
		$ext = array_pop($parts);
		return strtolower($ext);
	}

	static function get_ext_from_mime($mtype) {
		$parts = explode('/', $mtype);
		$ext = array_pop($parts);
		if ($ext = 'jpeg') {
			$ext = 'jpg';
		}
		return strtolower($ext);
	}

	static function make_thumbnail($dst_fn, $twidth, $theight, $src_fn, $crop = false) {
		$dst_fn = preg_replace('/[^.]+$/', 'jpg', $dst_fn);
		$size = @getimagesize($src_fn);
		if ($size === false) {
			return false;
		}

		// size of the original image
		list($owidth, $oheight) = $size;

		// by default, just copy the entire image
		$new_width = $owidth;
		$new_height = $oheight;

		if ($owidth > $twidth or $oheight > $theight) {
			// image is too large, we need to touch it

			if (!$crop) {
				// we want to downscale the image so it fits in the target rect
				// this means fitting the side that's "furthest" from the target
				if ( ($owidth/$twidth) > ($oheight/$theight) ) {
					// we need to fit the width
					$new_width = $twidth;
					$new_height = round(($oheight/$owidth)*$new_width);

				}
				else {
					$new_height = $theight;
					$new_width = round(($owidth/$oheight)*$new_height);
				}
			}
			else {
				// cropping
				// this means fitting the side that's closest to the target
				// so we dont have to downscale as much
				if ( ($owidth/$twidth) < ($oheight/$theight) ) {
					$new_width = $twidth;
					$new_height = round(($oheight/$owidth)*$new_width);
				}
				else {
					$new_height = $theight;
					$new_width = round(($owidth/$oheight)*$new_height);
				}

				// this way, we might not fit in the target, account for that
				if ($new_height > $theight) {
					$oheight *= ($theight/$new_height);
					$new_height = $theight;
				}
				if ($new_width > $twidth) {
					$owidth *= ($twidth/$new_width);
					$new_width = $twidth;
				}
			}
		}

		$thumb = imagecreatetruecolor($new_width, $new_height);
		$background_color = imagecolorallocate ($thumb, 255, 255, 255);
		imagefill($thumb, 0, 0, $background_color);
		$org_image = @imagecreatefromstring(file_get_contents($src_fn));
		if (!$org_image) {
			debug('GD cannot read back the file, no thumbs!');
			return false;
		}
		$src_x = $src_y = 0;
		if ($crop==='centered') {
			$src_x = ($owidth/2)-($new_width/2);
		}
		imagecopyresampled($thumb, $org_image, 0, 0, $src_x, $src_y, $new_width, $new_height, $owidth, $oheight);
		imagedestroy($org_image);
		imagejpeg($thumb, $dst_fn,80);
		imagedestroy($thumb);
		@chmod($dst_fn, 0666);

		return true;
	}

	static function recalc_all_images($store_method, $args = array()) {
		$hlpm = '_recalc_all_images_'.$store_method;
		if (!method_exists(__CLASS__, $hlpm)) {
			error("img::recalc_all_images($store_method, ...): no such store method");
		}

		call_user_func(array(__CLASS__, $hlpm),
			config::$img_stores[$store_method],
			$args
		);
	}

	private static function collect_originals($orgdir) {
		$d = opendir($orgdir);
		if (!$d) {
			error("img::collect_originals($orgdir): unable to open dir");
		}
		$originals = array();
		while ($e = readdir($d)) {
			if ($e[0] == '.') {
				continue;
			}
			$hd = opendir($orgdir.$e);
			if (!$hd) {
				error("img::collect_originals($orgdir): unable to open dir: $orgdir$e");
			}
			while ($he = readdir($hd)) {
				if ($he[0] == '.') {
					continue;
				}
				$originals[] = $orgdir.$e.'/'.$he;
			}
			closedir($hd);
		}
		closedir($d);
		return $originals;
	}

	static function _recalc_all_images_local($config, $args) {
		$root = config::$static_dir.$config['path'];
		foreach ($config['sizes'] as $type => $sizes) {
			if (isset($args['type']) and $type != $args['type']) {
				continue;
			}

			$typedir = $root.$type.'/';
			$orgdir = $typedir.'original/';
			$all_original_files = self::collect_originals($orgdir);

			foreach ($all_original_files as $of) {
				$done_stuff = false;
				$s = ' '.$of.' -> '.implode(', ', array_keys($sizes))."\n";

				foreach ($sizes as $size_name => $dims) {
					if (isset($args['size']) and $size_name != $args['size']) {
						continue;
					}

					$org_fn = substr($of, strlen($orgdir));
					$dst = $typedir.$size_name.'/'.$org_fn;

					list(, $org_fn) = explode('/', $org_fn);
					list($org_fn, ) = explode('.', $org_fn);
					if (isset($args['filter']) and !isset($args['filter'][$org_fn])) {
						continue;
					}

					list($want_x, $want_y) = $dims; // resize to these and store
					$crop = false;
					if (isset($dims[2])) {
						$crop = !!$dims[2];
					}
					if (isset($dims[3])) {
						if (@$dims[3]['ondemand'] and (!isset($args['type']) or $type != $args['type'])) { // skip this, except if you asked for this type explicitly
							continue;
						}
					}
					$done_stuff = true;
					$s .= '  '.$dst."\n";
					if (!is_dir(dirname($dst))) {
						if (!mkdir(dirname($dst), 0777, true)) {
							error('cannot mkdir dst dir: '.$dst);
							stk_exit();
						}
					}
					if ($crop && !empty($dims[3]['cropmode'])) {
						$crop = $dims[3]['cropmode'];
					}
					self::make_thumbnail($dst, $want_x, $want_y, $of, $crop);
				}
				if ($done_stuff) {
					echo $s;
				}
			}
		}
	}

	static function _store_img_api($type, $file_struct, $config, $store_method, $this_size_only = null) {
		$ch = curl_init();
		$data = array(
			'picture' => '@'.$file_struct['tmp_name'],
			'real_name' => $file_struct['name'],
			'store_method' => $store_method,
			'this_size_only' => $this_size_only,
		);

		$url = config::$internal_image_api['url'].$type;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_USERPWD, config::$internal_image_api['user_pass']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$return = curl_exec($ch);
		$json = @json_decode($return, true);
		if (!$json) {
			return ''; // XXX
		}
		$key = $json['response'];
		$key = explode(':', $key, 3);
		if (count($key) == 3) {
			return $key[2];
		}
		return ''; // XXX
	}

	static function _remove_img_api($key, $type, $config) {
		// noop
	}

	static function _store_img_local($type, $file_struct, $config, $store_method, $this_size_only = null) {
		$old_umask = umask(0);

		$ext = self::get_ext($file_struct['name']);

		if ($this_size_only === null) {
			$uniq_name = uniqid('i');
			$new_name = $uniq_name.'.'.$ext;
			$dir_name = substr($new_name, 0, $config['hash_len']);

			if (!isset($config['sizes'][$type])) {
				error('img::_store_img_local(...): unknown image type: '.$type);
			}
			$size_defs = $config['sizes'][$type];
			$size_defs['original'] = true; // always save the original image

			$tmp_path = $file_struct['tmp_name'];
		}
		else {
			if (!isset($config['sizes'][$type]) or !isset($config['sizes'][$type][$this_size_only])) {
				error('img::_store_img_local(...): unknown image type/size: '.$type.'/'.$this_size_only);
			}

			$uniq_name = basename($file_struct['name'], '.'.$ext);
			$new_name = $uniq_name.'.'.$ext;
			$dir_name = substr($new_name, 0, $config['hash_len']);

			$size_defs = array(
				$this_size_only => $config['sizes'][$type][$this_size_only],
			);
			$tmp_path = $file_struct['tmp_name'];
		}

		foreach ($size_defs as $size => $dims) {
			$path = config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/';
			if (!is_dir($path)) {
				if (!mkdir($path, 0777, true)) {
					error("img::_store_img_local(...): cannot mkdir $path");
				}
			}

			if ($dims === true) { // just store the file here
				if ($file_struct['tmp_name'] != $path.$new_name) { // if it's not already there
					if (!rename($file_struct['tmp_name'], $path.$new_name)) {
						debug("could not move {$file_struct['tmp_name']} to $path$new_name");
					}
					@chmod($path.$new_name, 0666);
				}
				$tmp_path = $path.$new_name; // if this isnt the first in $config['sizes'], we store the new name so the thumbgeneration can continue
			} else {
				list($want_x, $want_y) = $dims; // resize to these and store
				$crop = false;
				if (isset($dims[2])) {
					$crop = !!$dims[2];
				}
				if (isset($dims[3])) {
					if ($this_size_only === null and @$dims[3]['ondemand']) { // skip this
						continue;
					}
				}
				if ($crop && !empty($dims[3]['cropmode'])) {
					$crop = $dims[3]['cropmode'];
				}
				self::make_thumbnail($path.$new_name, $want_x, $want_y, $tmp_path, $crop);
			}
		}
		
		umask($old_umask);

		return $new_name;
	}

	// this function leaves off the static rev intentionally, as the pics this handles are not design-related but content.
	static function _get_url_local($key, $type, $size, $config) {
		if (!isset($config['sizes'][$type]) 
			or ($size != 'original' 
			and !isset($config['sizes'][$type][$size])))
	   	{
			error("img::_get_url_local($key, $type, $size, ...): unknown type = $type or size = $size");
		}
		$dir_name = substr($key, 0, $config['hash_len']);
		$original_key = $key;
		if ($size != 'original') {
			$key = preg_replace('/[^.]+$/', 'jpg', $key);
		}

		$url = false;
		if (is_array(config::$static_baseurls) and !empty(config::$static_baseurls)) {
			$sum = 0;
			foreach (unpack("c*",$key) as $c) {
				$sum+=$c;
			}
			$staticroot = config::$static_baseurls[$sum%count(config::$static_baseurls)];
		} else {
			$staticroot = config::$webroot;
		}

		if (!config::$do_image_stat or file_exists(config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key)) {
			$url = $staticroot.config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key;
		} else {
			if (!empty($config['sizes'][$type][$size][3]['ondemand'])) {
				self::restore_img(implode(':', array($type, 'local', $original_key)), $type, $size, 'local');
				$new_config = $config;
				unset($new_config['sizes'][$type][$size][3]['ondemand']);
				return self::_get_url_local($original_key, $type, $size, $new_config);
			}

			$key = preg_replace('/[^.]+$/', 'png', $key); // fallback to png
			if (file_exists(config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key)) {
				$url = $staticroot.config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key;
			}
		}

		return $url;
	}

	static function _get_file_local($key, $type, $size, $config) {
		if (!isset($config['sizes'][$type]) 
			or ($size != 'original' 
			and !isset($config['sizes'][$type][$size])))
	   	{
			error("img::_get_file_local($key, $type, $size, ...): unknown type = $type or size = $size");
		}
		$dir_name = substr($key, 0, $config['hash_len']);
		if ($size != 'original') {
			$key = preg_replace('/[^.]+$/', 'jpg', $key);
		}
		return config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key;
	}

	static function _remove_img_local($key, $type, $config) {
		$dir_name = substr($key, 0, $config['hash_len']);
		foreach ($config['sizes'] as $size => $_dummy) {
			$fn = config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key;
			@unlink($fn);
		}
	}

	static function _get_bytes_local($key, $type, $size, $fetch_meta, $config, $store_method) {
		if (!isset($config['sizes'][$type]) 
			or ($size != 'original' 
			and !isset($config['sizes'][$type][$size])))
	   	{
			error("img::_get_bytes_local($key, $type, $size, ...): unknown type = $type or size = $size");
		}
		$dir_name = substr($key, 0, $config['hash_len']);
		if ($size != 'original') {
			$key = preg_replace('/[^.]+$/', 'jpg', $key);
		}
		$path = config::$static_dir.$config['path'].$type.'/'.$size.'/'.$dir_name.'/'.$key;

		if (!file_exists($path)) {
			if ($size == 'original') { // maybe we lost the original extension off the key
				$path = preg_replace('/[^.]+$/', '*', $path);
				$candidates = glob($path);
				if (count($candidates) == 1) {
					$path = $candidates[0];
				}
				else {
					return array();
				}
			}
			else {
				return array();
			}
		}

		$imgdata = getimagesize($path);
		$meta = array(
			'size' => filesize($path),
			'mime' => $imgdata['mime'],
			'w' => $imgdata[0],
			'h' => $imgdata[1],
		);
		return array(
			'bytes' => file_get_contents($path),
			'meta' => $meta,
		);
	}

	static function _get_bytes_api($key, $type, $size, $fetch_meta, $config, $store_method) {
		$url = config::$internal_image_api['url'].'get/bytes?'.http_build_query(array(
			'key' => implode(':', array($type, $store_method, $key)),
			'size' => $size,
		), null, '&');

		$response = curl_get($url, array(), config::$internal_image_api['user_pass'], true, null, true);

		$meta = array();
		foreach ($response['headers'] as $k => $v) {
			if (strpos($k, 'X-Imgapi-') === 0) {
				$meta[ substr($k, 9) ] = $v;
			}
		}

		return array(
			'bytes' => $response['content'],
			'meta' => $meta,
		);
	}

	static function _get_bytes_facebook($key, $type, $size, $fetch_meta, $config, $store_method) {
		$url = self::_get_url_facebook($key, $type, $size, $config);

		config::$use_rld = false; // don't you DARE!
		$response = curl_get($url, array(), '', true, null, true);

		$hdr = $response['headers'];
		$meta = array();
		if (!empty($hdr['Content-Type'])) {
			$meta['mime'] = $hdr['Content-Type'];
		}
		if (!empty($hdr['Content-Length'])) {
			$meta['size'] = $hdr['Content-Length'];
		}

		return array(
			'bytes' => $response['content'],
			'meta' => $meta,
		);
	}

	static function _store_img_facebook($type, $file_struct, $config, $store_method, $dummy = null) {
		return $file_struct['name'];
	}

	static function _remove_img_facebook($key, $type, $config) {
		// nothing since we hotlink facebook stuff
	}

	static function _get_file_facebook($key, $type, $size, $config) {
		// nothing since we hotlink facebook stuff
	}

	static function _get_url_facebook($key, $type, $size, $config) {
		$wanted_size = $config['sizes'][$type][$size];
		$all_sizes = array_values($config['sizes'][$type]);

		if (preg_match('!\?type=(?:'.implode('|', $all_sizes).')$!', $key)) {
			$url = preg_replace('!\?type=\w+$!', '?type='.$wanted_size, $key);
		}
		else if (strpos($key, 'http://graph.facebook.com/') === 0) {
			$url = $key.'?type='.$wanted_size;
		}
		else {
			$url = $key;
		}
		return str_replace('http://','//',$url);
	}

	static function _store_img_twitter($type, $file_struct, $config, $store_method, $dummy = null) {
		return $file_struct['name'];
	}

	static function _remove_img_twitter($key, $type, $config) {
		// nothing since we hotlink twitter stuff
	}

	static function _get_file_twitter($key, $type, $size, $config) {
		// nothing since we hotlink twitter stuff
	}

	static function _get_url_twitter($key, $type, $size, $config) {
		// no need for magic here i think..
		return $key;
	}

	static function _store_img_temp($type, $file_struct, $config, $store_method, $dummy = null) {
		$ext = self::get_ext($file_struct['name']);
		$tempfile = tempnam($config['dir'], $config['prefix']);
		$tmp_path = $file_struct['tmp_name'];
		copy($tmp_path, $tempfile);
		return $tempfile;
	}

	static function _remove_img_temp($key, $type, $config) {
		if (strpos($key, $config['dir']) !== 0) {
			return;
		}
		@unlink($key);
	}

	static function _get_url_temp($key, $type, $size, $config) {
		// no urls
	}

	static function _get_file_temp($key, $type, $size, $config) {
		if (strpos($key, $config['dir']) !== 0) {
			return '';
		}
		return $key;
	}

	static function _get_bytes_temp($key, $type, $size, $fetch_meta, $config, $store_method) {
		if (strpos($key, $config['dir']) !== 0) {
			return array();
		}

		$imgdata = getimagesize($key);
		$meta = array(
			'size' => filesize($key),
			'mime' => $imgdata['mime'],
			'w' => $imgdata[0],
			'h' => $imgdata[1],
		);
		return array(
			'bytes' => file_get_contents($key),
			'meta' => $meta,
		);
	}

	static function _store_img_static($type, $file_struct, $config, $store_method, $dummy = null) {
		// wont.
	}
	static function _get_url_static($key, $type, $size, $config) {
		return config::$webroot.config::$static_dir.$key;
	}
	static function _remove_img_static($key, $type, $config) {
		// wont.
	}
	static function _get_file_static($key, $type, $size, $config) {
		return config::$static_dir.$key;
	}
	static function missing($type, $size) {
		$key = 'static/img/missing/'.$type.'_'.$size.'.jpg';
		return make_static_url($key);
	}
}
