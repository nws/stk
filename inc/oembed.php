<?php

class oembed {
	static $providers;
	public $info;

	function __construct($url) {
		self::setup_providers();
		$request_data = $this->get_provider($url);
		if (!$request_data) {
			throw new Exception("Unknown video provider for url: $url");
		}

		if ($info = $this->fetch_info($request_data)) {
			$info = $this->store_image($info);
			$this->info = $info;
			return;
		}
		throw new Exception("unable to fetch video info for url: $url");
	}

	private function get_provider($url) {
		foreach (self::$providers as $provider => $pdef) {
			if (strpos($url, $provider) === false) {
				continue;
			}
			if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
				continue;
			}
			if ($normalized_url = $pdef['match_and_normalize_url']($url)) {
				return array($provider, $normalized_url);
			}
		}
	}

	private function store_image($info) {
		$thumb_url = $info->thumbnail_url;
		$key = img::store_img_url($thumb_url, 'board_item', 'local');
		$info->thumbnail_url = imageurl($key, 'original');
		return $info;
	}

	private function fetch_info($rq) {
		$oembed_url = strtr(self::$providers[ $rq[0] ]['oembed_api_template'], array(
			'@url' => urlencode($rq[1]),
		));
		$info_json = curl_get($oembed_url);
		$info = @json_decode($info_json);
		return $info;
	}

	private static function setup_providers() {
		if (!self::$providers) {
			self::$providers = array(
				'vimeo' => array(
					'match_and_normalize_url' => function($url) { return $url; },
					'oembed_api_template' => 'http://vimeo.com/api/oembed.json?url=@url',
				),
				'youtube' => array(
					'match_and_normalize_url' => function($url) {
						$parsed_url = parse_url($url);
						if (!empty($parsed_url['query'])) {
							return $url;
						}
						if (!empty($parsed_url['path'])) {
							$path = explode('/', $parsed_url['path']);
							$path = array_pop($path);
							return "http://youtube.com/watch?v=".$path;
						}
					},
					'oembed_api_template' => 'http://www.youtube.com/oembed?url=@url&format=json',
				),
			);
		}
	}
}

