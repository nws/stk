<?php

function inc() {
	static $done = array();
	$mods = func_get_args();
	call_args_flatten($mods);
	foreach ($mods as $m) {
		if ($m[0] == '/') {
			$m = substr($m, 1);
		}
		if (isset($done[$m])) {
			continue;
		}
		require 'inc/'.$m.'.php';
		$done[$m] = 1;
	}
}

function lib() {
	static $done = array();
	$libs = func_get_args();
	call_args_flatten($libs);
	foreach ($libs as $l) {
		if (isset($done[$l])) {
			continue;
		}
		require 'lib/'.$l.'.php';
		$done[$l] = 1;
	}
}

function error($s) {
	trigger_error($s, E_USER_ERROR);
}

function warning($s) {
	trigger_error($s, E_USER_WARNING);
}
 
function config($config) {
	require 'cfg/'.$config.'.php';
	config::$whoami = $config;
	if (!empty($_SERVER["HTTPS"])) {
		config::$baseurl = str_replace('http://','https://',config::$baseurl);
		foreach (config::$static_baseurls as &$u) {
			$u = str_replace('http://','https://',$u);
		}
		unset($u);
	}
	if (config::$baseurl === false) {
		config::$baseurl = 'http://'.config::$host.'/';
	}
	if (config::$cookie_domain === false) {
		config::$cookie_domain = '.'.config::$host;
	}
	inc(config::$inc);
}

function call_args_flatten(&$args) {
	if (count($args) == 1 and is_array($args[0])) {
		$args = $args[0];
	}
}

/* these two start up the entire framework, 
 * they only differ in how they route their stuff
 */
function stk_bandwagon_web($sites, $init_debug_val = null) {
	if ($_SERVER['REQUEST_METHOD'] == 'BREW') {
		header("HTTP/1.1 418 I'm a teapot", true, 418);
		header("X-Poem: I'm a little teapot,", true);
		header("X-Poem: Short and stout,", false);
		header("X-Poem: Here is my handle (one hand on hip),", false);
		header("X-Poem: Here is my spout (other arm out with elbow and wrist bent),", false);
		header("X-Poem: When I get all steamed up,", false);
		header("X-Poem: Hear me shout,", false);
		header("X-Poem: Tip me over and pour me out! (lean over toward spout)", false);
		header("X-Illustration-URL: http://icanhascheezburger.com/2009/01/13/funny-pictures-little-teapot-short-and-stout/");
		header('X-Powered-By: Sunshine and Unicorn Farts');
		header('Content-Type: Coffee');
		exit /* EXIT_OK */;
	}
	if (!empty($_SERVER["HTTP_X_POUND"]) && strpos($_SERVER["HTTP_X_POUND"],'HTTPS')!==false) {
		$_SERVER["HTTPS"] = 'on';
	}

	// init the framework
	stk_init($init_debug_val);

	// load libs
	stk_libs();

	list($page, $args) = stk_route_web($sites);

	header('X-STK-Backend: '.config::$serverid);
	
	// init session and stash
	stk_globals($page, $args);

	// No surprise to see Sven-Göran Eriksson in the crowd.
	stk_setup(); // call setup on stuff

	// start the framework
	stk_start($page, $args);

	// exit cleanly
	stk_exit();
}

function stk_bandwagon_cli($config, $path, $args) {
	ini_set('session.bug_compat_42', 0);
	stk_init(1);

	error_reporting(E_ALL);

	stk_libs();

	$page = stk_route_cli($config, $path);

	stk_set_cli_error_log(config::$cli_error_log_file);

	if (getenv('STKCRON')) {
		fclose(STDOUT);
		fclose(STDERR);
		fclose(STDIN);
		$_STDIN = fopen('/dev/null', 'r');
		$_STDOUT = fopen('/dev/null', 'w');
		$_STDERR = fopen('/dev/null', 'w');
	}

	debug('--STARTRQ CLI-- '.$page." ".$path);

	stk_globals($page, $args);

	stk_setup();

	stk_start($page, $args);

	stk_exit();
}

function stk_init($debug_val = null) {
	global $_stk_start_time, $_stk_scache;
	$_stk_scache = array();
	$_stk_start_time = microtime(true);
	mb_internal_encoding('UTF-8');
	debug_set($debug_val);
	debug_usage('post init');
}

function stk_start($page, $args) {
	header('Content-Type: text/html; charset=utf-8'); // overridable anytime later
	$output = t::call($page, $args);
	$output = stk_output_filter($output, true); // automatic call
	echo $output;
	trigger::fire('end_of_output');
	$pi = stash()->postinject;
	if (!empty($pi)) {
		echo implode('', $pi);
	}
}

function stk_manual_output_filter($set = null) {
	static $manual = false;
	if ($set !== null) {
		$manual = $set;
	}
	return $manual;
}

function stk_output_filter($output, $automatic_call = false) {
	if ($automatic_call && stk_manual_output_filter()) {
		return $output;
	}

	if (!empty(config::$output_filters)) {
		foreach (config::$output_filters as $of => $on) {
			if ($on) {
				$output = $of($output);
			}
		}
	}
	return $output;
}

function stk_globals($page, $args) {
	struct::load('session', config::$session_class);

	struct::load('stash', config::$stash_class);
	$stash = stash();
	$stash->args = $args;
	$stash->page = $page;
	$stash->roles = new roles('guest'); // create the global roles object for the acl

	$stash->location = url_current();

	if (strpos($page, config::$sitedir) === 0) {
		$stash->url = substr($page, strlen(config::$sitedir));
	} else {
		$stash->url = null; // page isnt accessible directly from url anyway
	}
}

function stk_libs() {
	lib(
		'rewrite',
		'url',
		'redirect',
		'route', 
		'tile',
		'fi', 
		'sel', 
		'mod',
		'struct',
		'stash',
		'session-mysql',
		'session',
		'models',
		'env',
		'trigger',
		'html',
		'acl',
		'input',
		'img',
		'video',
		'apc-compat'
	);
	debug_usage('post lib()');
}

function stk_route_cli($config, $path) {
	config($config);

	$old_sitedir = config::$sitedir;
	config::$sitedir = ''; // so we can access all tiles from cli
	list($page) = route($path);
	config::$sitedir = $old_sitedir;
	return $page;
}

function stk_route_web($sites) {
	list($config, $path) = rewrite($sites);

	config($config);

	debug_usage('post config()');

	list($page, $args, $success) = route($path);

	if (!$success && !empty(config::$dyn_routes)) {
		$dyn_route_keys = array_keys(config::$dyn_routes);
		$dyn_route_count = count(config::$dyn_routes);
		$i = 0;
		do {
			$key = $dyn_route_keys[$i];
			list($page, $args, $success) = route($key.'/'.$path);
			if ($success && !empty(config::$dyn_routes[$key])) {
				$success = call_user_func(config::$dyn_routes[$key], array(
					'page' => $page,
					'args' => $args,
				));
			}
		} while (!$success && ++$i < $dyn_route_count);
	}

	debug_usage('post route()');

	debug('--STARTRQ '.$_SERVER['REQUEST_METHOD'].'-- '.$page." ".$path);

	return array($page, $args);
}

function stk_setup() {
	//cache_init();
	if (isset(config::$locale)) {
		setlocale(LC_ALL, config::$locale);
	}
	models::setup();
	trigger::setup();
	t::setup();
	env::setup();
	acl::setup();
	input::setup();
	img::setup();
	debug_usage('post setup()-s');
}

// *NEVER* use exit() or die() in any sane circumstance
// when forwarding a user or handling output yourself, always call stk_exit() instead
function stk_exit($redirecting = false, $rv = 0) {
	if (!$redirecting) {
		unset_message();
	}
	trigger::fire('exit');

	if (!empty(fi::$query_pertile)) {
		debug('-- PER TILE --', fi::$query_pertile);
	}

	$q_per_ck = array();
	foreach (fi::$query_count_by_ck as $ck => $qc) {
		$q_per_ck[] = "$ck: $qc";
	}
	$q_per_ck = implode(', ', $q_per_ck);

	if (isset($GLOBALS['tr_count'])) {
		$tr_count_s = ' transaction count: '.$GLOBALS['tr_count'];
	} else {
		$tr_count_s = ' transaction count: 0';
	}
	debug("--ENDRQ-- q: ".fi::$query_count.", ".$q_per_ck."; mem: ".stk_memusage().'M, time: '.stk_timing().'sec'.$tr_count_s); // so it's clearer where a request ends in the logs
	session_write_close();
/*	if (fi::$in_transaction) {
		warning('fi has unfinished transaction! trc:'. $tr_count_s);
	}*/
	exit($rv) /* EXIT_OK */;
}

function stk_die($msg = '') {
	$msg = trim($msg);
	echo $msg, "\n";
	debug("DIED: $msg");
	return stk_exit(false, 1);
}

function stk_memusage($peak = true) {
	$m = ($peak
		? memory_get_peak_usage(1)
		: memory_get_usage(1));

	return round($m/(1024*1024), 2);
}

function stk_timing() {
	global $_stk_start_time, $_stk_last_timing_point;
	$now = microtime(true);
	if (!isset($_stk_last_timing_point)) {
		$_stk_last_timing_point = $now;
	}
	$s = round($now - $_stk_start_time, 3).'('.round($now-$_stk_last_timing_point, 2).')';
	$_stk_last_timing_point = $now;
	return $s;
}

function http_error($location, $code = '404 Not Found') {
	if (!is_array($location)) {
		$location = explode('/', $location);
	}
	array_unshift($location, '');
	stash()->args = $location;
	header("HTTP/1.0 $code");
	t::tmpl('smarty', config::$errordir.'e404');
}

function set_message($msg, $result = null, $args = null) { // 1 == true
	$msg = html_escape($msg);
	if (is_array($args)) {
		$msg = vsprintf($msg, $args);
	}
	if (isset(config::$msg_headers[$result])) {
		$msg = sprintf(config::$msg_headers[(int)$result], $msg);
	}
	session()->messages[] = $msg;
	session()->session_was_modified();
}

function get_message() {
	return session()->messages;
}

function format_message() {
	$msgs = get_message();
	if (count($msgs)) {
		return "<div id='status_bar'>".h::listing($msgs)."</div>";
	}
	return '';
}

function keep_message() {
	global $_stk_keep_message;
	$_stk_keep_message = true;
}

function unset_message() {
	global $_stk_keep_message;
	if ($_stk_keep_message) {
		return;
	}
	if (isset(session()->messages[0])) {
		session()->messages = array();
		session()->session_was_modified();
	}
}

function form_error($error) {
	if (!t::has('form_error')) {
		t::def('form_error', array($error));
	} else {
		t::extend('form_error', $error);
	}
}

function stk_config_js() {
	$vars = array();
	foreach (array('rootdir', 'sitedir', 'errordir', 'webroot') as $v) {
		$vars[$v] = config::$$v;
	}
	$vars = json_encode($vars);
	return h::script(null, 'var config = '.$vars.';');
}

function debug_set($state = null) {
	global $_debug_on;
	$old = intval($_debug_on);
	if ($state !== null) {
		$_debug_on = intval($state);
	} else if (isset($_REQUEST['DON'])) {
		$_debug_on = intval($_REQUEST['DON']);
	} else {
		//$_debug_on = intval(@$_SESSION['DON']);
	}
	//$_SESSION['DON'] = $_debug_on;
	if ($_debug_on) {
		error_reporting(E_ALL);
	} else {
		error_reporting(0);
	}
	return $old;
}

// bt_debug(array('file', 'regex matching file'), ....)
function bt_debug() {
	$args = func_get_args();
	$match = array_shift($args);
	list($what, $re) = $match;

	$bt = 'NOT FOUND';
	$trace = array_reverse(debug_backtrace());

	foreach ($trace as $i => $t) {
		if (preg_match($re, $t[$what]) && $i > 0) {
			$t = $trace[$i-1];
			$bt = $t['file'].':'.$t['line'].':'.$t['function'];
			break;
		}
	}

	$args[0] = $bt.' - '.$args[0];
	call_user_func_array('debug', $args);
}

function debug($s) {
	global $_debug_on;
	if (!$_debug_on) {
		return;
	}
	$s = '['.stk_memusage().'/'.stk_timing().']'.$s;
	if (class_exists('t') and t::current_tile()) {
		$s = '{'.t::current_tile().'} '.$s;
	} else {
		$s = '{NONE?} '.$s;
	}
	$a = func_get_args();
	array_shift($a);
	if (!empty($a)) {
		$a = print_r($a, true);
		#$a = preg_replace('/[^\n\S]+/', ' ', $a);
		$a = explode("\n", $a);
		foreach ($a as $_a) {
			#$_a = trim($_a);
			if ($_a) {
				stk_error_log($s.': '.$_a);
			}
		}
	} else {
		stk_error_log($s);
	}
}

function stk_set_cli_error_log($file = null) {
	static $stk_cli_error_log;
	if ($file !== null) {
		$stk_cli_error_log = $file;
		@chmod($file,0666);
		ini_set('error_log', $file);
	}
	return $stk_cli_error_log;
}

function stk_error_log($s) {
	$_stk_cli_error_log = stk_set_cli_error_log();
	if ($_stk_cli_error_log) {
		$date = '['.date('D M j H:i:s Y').']';
		error_log($date.' '.$s."\n", 3, $_stk_cli_error_log);
	}
	else {
		if (class_exists('config') && !empty(config::$message_admins_with_debug) && struct::is_loaded('stash') && stash()->user_id != -1 && acl::check('adminpage', 'full')) {
			set_message($s);
		}
		error_log($s);
	}
}

function debug_usage($key) {
	return; // disabled
	global $_debug_on;
	if (!$_debug_on) {
		return;
	}
	global $_stk_start_time;
	$pusage = round(memory_get_peak_usage(1)/(1024*1024), 2);
	$usage = round(memory_get_usage(1)/(1024*1024), 2);
	$now = microtime(true);
	debug("--USAGE $key-- mem: ".$usage.'M, pmem: '.$pusage.', time: '.round($now - $_stk_start_time, 3).'sec');
}

function db_connect() {
	$conn_data = config::$db;
	foreach ($conn_data as $ck => &$cd) {
		if (empty($cd['user'])) {
			$cd['user'] = config::$whoami.config::$serverid;
		}
	}
	unset($cd);
	
	fi::multi_connect($conn_data);
}


function flush_cache() {
	cache_clear_maint();
}

function check_maintenance() {
	if (isset(config::$maintenance_mode) && (config::$maintenance_mode)) {
		set_message("We are in maintenance mode. Please check back later.");
		redirect('');
	}
}

function stk_host() {
	if (isset(config::$host)) {
		$url = config::$host;
	} else if (isset($_SERVER['HTTP_HOST']) and $_SERVER['HTTP_HOST']) {
		$url = $_SERVER['HTTP_HOST'];
	} else {
		$url = $_SERVER['SERVER_NAME'];
	}
	return $url;
}

function debug_time($s) {
	global $_debug_time;
	if (!is_array($_debug_time)) {
		$_debug_time = array();
	}
	if (isset($_debug_time[$s])) {
		$now = microtime(true);
		$then = $_debug_time[$s];
		unset($_debug_time[$s]);
		debug($s.': '.($now-$then));
	} 
	else {
		$_debug_time[$s] = microtime(true);
	}
}

function debug_devel() {
	static $devs = array(
		'127.0.0.1' => 1,
	);
	return (isset($devs[$_SERVER['REMOTE_ADDR']]) && $devs[$_SERVER['REMOTE_ADDR']]);
}

function sm_config($key) {
	return config::$$key;
}

// wrappers for a very simple static cache (do not put nulls in it)
function &scache_get($key) {
	static $null = null;
	global $_stk_scache;
	if (isset($_stk_scache[$key])) {
		return $_stk_scache[$key];
	}
	else {
		return $null;
	}
}

function scache_put($key, $data) {
	global $_stk_scache;
	$_stk_scache[$key] = $data;
}

// somewhat like echo, except it only works if we're calling the script by hand
function scout() {
	$a = func_get_args();
	if (php_sapi_name() == 'cli' and posix_isatty(STDOUT)) {
		array_unshift($a, STDOUT);
		call_user_func_array('fwrite', $a);
	}
}

function stk_set_include_path($entry_point_path) {
	$site_path = dirname(realpath($entry_point_path));
	$stk_path = dirname(realpath(__FILE__));
	define('SITE_PATH', rtrim($site_path, '/').'/');
	define('STK_PATH', rtrim($stk_path, '/').'/');

	$path_suffixes = array(
		'.',
		'inc/',
		'inc/pear',
		'lib',
		'smarty',
		'tiles',
	);

	$paths = array();
	foreach (array($site_path, $stk_path) as $pfx) {
		foreach ($path_suffixes as $ps) {
			$paths[] = "$pfx/$ps";
		}
	}

	ini_set('include_path', implode(':', $paths));
}

function stk_start_web($entry_point_path) {
	set_time_limit(60);
	error_reporting(E_ALL|E_STRICT);

	stk_set_include_path($entry_point_path);

	ini_set('session.bug_compat_warn', 'off');
	ini_set('gd.jpeg_ignore_warning', 1);

	return stk_bandwagon_web(array('site'), 2);
}

function stk_start_cli($entry_point_path) {
	global $argc, $argv;

	error_reporting(E_ALL|E_STRICT);

	stk_set_include_path($entry_point_path);

	ini_set('log_errors', 1);
	ini_set('session.bug_compat_warn', 'off');
	ini_set('gd.jpeg_ignore_warning', 1);

	if ($argc < 2) {
		die("cannot init stk, usage: run.php <tile> [args]\n");
	}

	if (!preg_match('|^tiles/(.*?)(?:\.php)?$|', $argv[1], $m)) {
		die("{$argv[1]} looks bad\n");
	}

	array_shift($argv); // progname
	array_shift($argv); // path

	$path = $m[1];
	$args = &$argv;

	return stk_bandwagon_cli('site', $path, $args);
}
