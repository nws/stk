<?php

# url spec:
# simple:
# 	string: !anything -- passed through untouched, you know what you doing (query is appended)
#   string: 'foo/bar/baz'
#   array: array('foo', 'bar', 'baz')
# full:
#   hash: array('path' => <simple>, 'query' => array('jump' => 1))
# what no longer works: 
# 	array('foo/bar', 'baz')

// take an url spec, return a proper url
// NORMALIZE
function url($spec, $absolute = false) {
	$spec = url_normalize($spec);
	$is_url_external = $spec['external'];

	if (!isset($spec['path']) or !isset($spec['query'])) {
		error("bad url spec passed to url(): ", $spec);
	}

	if ($is_url_external) {
		$url = $spec['path'];
	} else {
		$path = $spec['path'];
		$path = array_map('urlencode', $path);
		$url = url_root().implode('/', $path);
	}

	if (!empty($spec['query'])) {
		$sep_char = '?';
		if ($is_url_external and strpos($url, '?') !== false) {
			$sep_char = '&';
		}

		if (is_array($spec['query'])) {
			$params = array();
			foreach ($spec['query'] as $k => $v) {
				$params[] = urlencode($k).'='.urlencode($v);
			}
			$params = implode('&', $params);

			$url .= $sep_char.$params;
		} else { // if it's a string, just concat it (being mindful of sep_char)
			if ($spec['query'][0] != '?' and $spec['query'][0] != '&') {
				$url .= $sep_char;
			}
			$url .= $spec['query'];
		}
	}

	if ($spec['hash'] !== null) {
		$url .= '#'.urlencode($spec['hash']);
	}

	if (!$is_url_external and $absolute) {
		$url = url_host().$url;
	}

	return $url;
}

function track_url($spec, $absolute = true, $utm = array()) {
	$spec = url_seo(url_normalize($spec));
	if (!$spec['external']) {
		$spec['query'] += $utm;
	}
	return url($spec, $absolute);
}

// only url() will call this automatically. you must call it yourself if you want it.
function url_normalize($spec) {
	if (is_string($spec) && substr($spec,0,4)==='http') {
		$spec = array('path'=>$spec,'external'=>true);
		$is_url_external = true;
	} else {
		$is_url_external = (is_array($spec) and isset($spec['external'])) ? $spec['external'] : false;
	}

	if (!is_array($spec) or !isset($spec['path'])) { // spec is a simple array or string
		$spec = array(
			'path' => $spec,
			'query' => array(),
			'hash' => null,
			'external' => false,
		);
	} else {
		if (!isset($spec['hash'])) {
			$spec['hash'] = null;
		}
		if (!isset($spec['query'])) {
			$spec['query'] = array();
		} 
	}

	if (!$is_url_external and !is_array($spec['path'])) { // spec is a simple string
		if ($spec['path'] and $spec['path'][0] == '!') { // external url
			$is_url_external = true;
			$spec['path'] = substr($spec['path'], 1);
		} else {
			if (strlen($spec['path']) and $spec['path'][0] == '/') {
				$spec['path'] = substr($spec['path'], 1);
			}
			$spec['path'] = explode('/', $spec['path']);
			$spec['path'] = array_map('urldecode', $spec['path']);
		}
	} 

	$spec['external'] = $is_url_external;
	return $spec;
}

function url_parse($str) {
	$external = false;
	$hash = null;
	$parsed = parse_url($str);

	if (isset($parsed['host']) and $parsed['host'] != stk_host()) {
		$external = true;
	}
	$query = array();
	if (isset($parsed['query'])) {
		parse_str($parsed['query'], $query);
	}
	if (isset($parsed['fragment'])) {
		$hash = $parsed['fragment'];
	}

	$path = $parsed['path'];
	if ($external) {
		$scheme = (isset($parsed['scheme']) ? $parsed['scheme'] : 'http');
		$path = '!'.$scheme.'://'.$parsed['host'].$path;
	} else {
		if (strpos($path, url_root()) === 0) {
			$path = substr($path, strlen(url_root()));
		}
		$path = explode('/', $path);
		$path = array_map('urldecode', $path);
	}

	return array(
		'path' => $path,
		'query' => $query,
		'external' => $external,
		'hash' => $hash,
	);
}

function url_host() {
	return 'http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.stk_host();
}

function url_root() {
	return config::$webroot;
}

// return the current url we're at, in the most anal format.
function url_current() {
	if (isset($_SERVER['REQUEST_URI'])) {
		return url_parse($_SERVER['REQUEST_URI']);
	}
	if (isset($_SERVER['argv']) 
		and isset($_SERVER['argv'][1]) 
		and file_exists($_SERVER['argv'][1]))
	{
		$path = preg_replace('/\.php$/', '', $_SERVER['argv'][1]);
		return url_parse($path);
	}
	return url_parse('');
}

// in: stk_url
// out: stk_url, path changed so it has the little names on it.. ooo
function url_seo($url) {
	if (!isset($url['path'])) {
		$url = url_normalize($url);
	}
	$path = $url['path'];

	if (!$url['external'] 
		and count($path) > 2 
		and $path[1] == 'index' 
		and ((string)intval($path[2]) == (string)$path[2])) 
	{
		$show_names_prewarm = scache_get('make_link_show_names');
		$artist_names_prewarm = scache_get('make_link_artist_names');
		$venue_names_prewarm = scache_get('make_link_venue_names');
		$user_names_prewarm = scache_get('make_link_user_names');
		$festival_names_prewarm = scache_get('make_link_festival_names');

		$name = '';
		switch ($path[0]) {
		case 'artist':
			if (isset($artist_names_prewarm[$path[2]])) {
				$name = $artist_names_prewarm[$path[2]];
			}
			else {
				$name = models::get('artist')->get_artist_name($path[2]);
			}
			break;

		case 'show':
			if (isset($show_names_prewarm[$path[2]])) {
				$name = $show_names_prewarm[$path[2]];
			}
			else {
				$name = models::get('show')->get_show_name($path[2]);
			}
			break;

		case 'user':
			if (isset($user_names_prewarm[$path[2]])) {
				$name = $user_names_prewarm[$path[2]];
			}
			else {
				$name = models::get('user')->get_user_name($path[2]);
			}
			break;

		case 'venue':
			if (isset($venue_names_prewarm[$path[2]])) {
				$name = $venue_names_prewarm[$path[2]];
			}
			else {
				$name = models::get('venue')->get_venue_name($path[2]);
			}
			break;

		case 'festival':
			if (isset($festival_names_prewarm[$path[2]])) {
				$name = $festival_names_prewarm[$path[2]];
			}
			else {
				$name = models::get('show')->get_festival_name($path[2]);
			}
			break;

		default:
			error("bad path: ".print_r($path, 1));
		}

		if (!$name) {
			$name = '';
		}
		else {
			$name = urlize_string($name);
		}

		if ($name) {
			$path[2] = $path[2].'-'.$name;
		}
	}

	$url['path'] = $path;
	return $url;
}

// in: stk_url
// out: raw relative link
function make_link($url, $absolute = false, $from_template = false) { // array or string, hash or string
	if ($from_template and !empty(config::$output_filters['make_link_postfilter'])) {
		global $_make_link_urls;
		$data = array($url['path'], $url['query'], intval($absolute));
		$_make_link_urls[] = $url['path'];
		return '<!-- make_link:'.implode('#', $data).' -->';
	}
	else {
		$url = url_normalize($url);
		$url = url_seo($url);
		return url($url, $absolute);
	}
}

function make_link_postfilter($output) {
	global $_make_link_urls;
	if (empty($_make_link_urls)) {
		return $output;
	}
	$ids = array();
	foreach ($_make_link_urls as $url) {
		list($type, $id) = explode('/index/', $url);
		if (!$id) {
			continue;
		}
		$ids[$type][$id] = 1;
	}
	if (!empty($ids['show'])) {
		scache_put('make_link_show_names', models::get('show')->get_show_name(array_keys($ids['show'])));
	}
	if (!empty($ids['venue'])) {
		scache_put('make_link_venue_names', models::get('venue')->get_venue_name(array_keys($ids['venue'])));
	}
	if (!empty($ids['user'])) {
		scache_put('make_link_user_names', models::get('user')->get_user_name(array_keys($ids['user'])));
	}
	if (!empty($ids['artist'])) {
		scache_put('make_link_artist_names', models::get('artist')->get_artist_name(array_keys($ids['artist'])));
	}
	if (!empty($ids['festival'])) {
		scache_put('make_link_festival_names', models::get('show')->get_festival_name(array_keys($ids['festival'])));
	}

	$output = preg_replace_callback('/<!-- make_link:([^#]*)#([^#]*)#(\d+) -->/', 'make_link_postfilter_cb', $output);
	return $output;
}

function make_link_postfilter_cb($m) {
	return make_link(array('path' => $m[1], 'query' => $m[2]), $m[3], false); // not from template
}

function url_referer() {
	$ref = @$_SERVER['HTTP_REFERER'];
	if (!$ref) {
		$ref = '';
	}
	$url = url_parse($ref);
	if ($url['external']) {
		return url_parse('');
	}
	return $url;
}

// XXX remove this once lib/redirect is cleaned up as well. only place it's used
function stk_referer($format = true) {
	$url = url_referer();
	if ($format) {
		$url = url($url);
	}
	return $url;
}

function url_canonicalize($str) {
	static $defaults = array(
		'scheme' => 'http',
		'port' => 80,
	);
	if (strpos($str, '://') === false) {
		$str = 'http://'.$str;
	}
	$s = parse_url($str);

	$s += $defaults;

	$u = $s['scheme'].'://'.$s['host'];
	if ($s['port'] != 80) {
		$u .= ':'.$s['port'];
	}
	if (isset($s['path']) and $s['path'] !== '' and $s['path'] !== '/') {
		$u .= $s['path'];
	}
	if (isset($s['query']) and $s['query'] !== '') {
		$u .= '?'.$s['query'];
	}
	if (isset($s['fragment']) and $s['fragment'] !== '') {
		$u .= '#'.$s['fragment'];
	}
	return $u;
}
