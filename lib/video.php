<?php

class video {
	// add a new type here too...
	static $types = array('youtube', 'vimeo');

	static function setup() {

	}

	private static function split_key($video_key) {
		if (strpos($video_key, ':') === false) {
			return array(null, null);
		}

		$parts = explode(':', $video_key, 2);

		if (!in_array($parts[0], self::$types)) {
			return array(null, null);
		}

		return $parts;
	}

	static function get_thumb($video_key,$size='default') {
		list($t, $key) = self::split_key($video_key);
		if (!$t) {
			return;
		}

		$thumb_fn = '_get_thumb_'.$t;
		return self::$thumb_fn($key,$size);
	}

	static function get_embed($video_key, $type = 'object', $params=array()) {
		list($t, $key) = self::split_key($video_key);
		if (!$t) {
			return;
		}
		$embed_fn = '_get_embed_'.$t;
		return self::$embed_fn($key, $type, $params);
	}

	static function show($video) {
		$video = html_entity_decode($video);
		$video = trim($video);
		if (strpos($video, '<object') === 0 || strpos($video, '<iframe') === 0) {
			return $video;
		}
		if (strpos($video, 'http') === 0) {
			$video = self::parse($video);
		}
		$r = self::get_embed($video);
		return $r;
	}

	static function get_link($video_key) {
		list($t, $key) = self::split_key($video_key);
		if (!$t) {
			return;
		}
		$link_fn = '_get_link_'.$t;
		return self::$link_fn($key);
	}


	static function parse($video_url) {
		$rv = null;
		$t = null;
		foreach (self::$types as $t) {
			$parse_fn = '_parse_'.$t;
			$rv = self::$parse_fn($video_url);
			if ($rv !== null) { // for he IS the kwisatz haderach!
				break;
			}
		}
		if ($rv === null) { // none of them matched.
			return null;
		}
		return $t.':'.$rv;
	}

	/* SPECIFIC TYPES */
	static function _parse_youtube($video_url) {
		if (!preg_match('|youtube\\.com/watch\\?v=([\w-]+)|', $video_url, $m) && !preg_match('|youtu\\.be/([\w-]+)|',$video_url,$m)) {
			return;
		}
		
		return $m[1];
	}

	static function _get_thumb_youtube($key,$size='default') {
		// default is 120 wide
		if ($size == 'large') {
			$size = 'hqdefault';
		}
		if (!in_array($size,array('default','hqdefault'))) {
			$size = 'default';
		}
		return "http://i.ytimg.com/vi/$key/$size.jpg";
	}

	static function _get_embed_youtube($key, $type = 'object', $params=array()){
		$autoplay = true;
		if (substr($key,-11,11)=='_noautoplay') {
			$key = substr($key,0,-11);
			$autoplay = false;
		}
//		'object' type videos seem buggy, all changed to 'iframe', the wickedness with the $type is to keep all videos the right size
		if ($type == 'iframe') {
			$sx = 640;
			$sy = 510;
		} else {
			$sx = 602;
			$sy = 344;
		}
		if (isset($params['w'])) {
			$sx = $params['w'];
		} 
		if (isset($params['h'])) {
			$sy = $params['h'];	
		}
		$src = 'http://www.youtube.com/embed/'.$key;
		if ($autoplay) {
			$src .= '?autoplay=1';
		}
		if (strpos($src,'?')!==false) {
			$src .= '&';
		} else {
			$src .= '?';
		}
		$src .= 'wmode=transparent';

		return '<iframe title="YouTube video player" class="youtube-player" 
			type="text/html" width="'.$sx.'" height="'.$sy.'" 
			src="'.$src.'" frameborder="0" 
			allowFullScreen></iframe>';
	}

	static function _parse_vimeo($video_url) {
		if (!preg_match('|vimeo.com/[^#]*#(\d+)|', $video_url, $m)) {
			if (!preg_match('|vimeo.com/(\d+)|', $video_url, $m)) {
				return;
			}
		}

		return $m[1];
	}

	static function _get_thumb_vimeo($key,$size='default') {
		$json = @json_decode(file_get_contents( 'http://vimeo.com/api/v2/video/'.$key.'.json'), true);
		if (!$json) {
			return '';
		}
		if ($size == 'default') {
			$size = 'thumbnail_medium';
		}

		if ($size == 'large') {
			$size = 'thumbnail_large';
		}

		$data = $json[0];

		// medium == 100 wide
		$ttypes = array( 'thumbnail_medium', 'thumbnail_small', 'thumbnail_large' );
		if (in_array($size, $ttypes)) {
			if (isset($data[$size])) {
				return $data[$size];
			}
		}
		// fallback
		foreach($ttypes as $type) {
			if (isset($data[$type])) {
				return $data[$type];
			}
		}

		return '';

	}

	static function _get_embed_vimeo($key, $type = 'object', $params=array()) {
		$autoplay = true;
		if (substr($key,-11,11)=='_noautoplay') {
			$key = substr($key,0,-11);
			$autoplay = false;
		}
		if (empty($params['w'])) {
			$params['w'] = 602;
		}
		if (empty($params['h'])) {
			$params['h'] = 344;
		}
		return '<iframe src="http://player.vimeo.com/video/'.$key.'?title=0&amp;byline=0&amp;portrait=0" width="'.$params['w'].'" height="'.$params['h'].'" frameborder="0"></iframe>';
	}

	static function _get_link_youtube($key) {
		return 'http://www.youtube.com/watch?v='.$key;
	}
	static function _get_link_vimeo($key) {
		return 'http://vimeo.com/'.$key;
	}
}
