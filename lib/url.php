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
	$spec = url_normalize($spec);
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
// out: raw relative link
function make_link($url, $absolute = false, $from_template = false) { // array or string, hash or string
	$url = url_normalize($url);
	return url($url, $absolute);
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
