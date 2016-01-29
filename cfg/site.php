<?php

// make 100% sure this is the same TZ mysql is set to, or there will be untold grief
// and the lamentations of thousands

define('COUNTRY_ID_USA', 840);
define('COUNTRY_ID_UK', 826);

class config {
	static $db_charset = 'utf8'; // set this to utf8mb4 if you have utf8mb4 columns in the db and want _actual_ utf8 from mysql
	static $timezone = 'America/New_York';
	static $admin_user = 'admin';
	static $admin_pass = 'admin';
	static $tempdir = '/tmp/';

	static $date_formats = array(
		'default' => '%B %e, %Y at %I:%M %p'
	);

	static $serverid = 0;
	static $whoami = 'stk';
	static $instance_type = 'prod';

	static $dbck = array(
		'ro' => 'default',
		'rw' => 'default',
	);

	static $switch_to_rw_db_after_write = false;

	// database configuration 
	static $db = array(
		'default' => array(
			'host' => 'localhost',
			'port' => 0,
			'user' => 'root', # when this is empty(), a username is generated from config::$whoami.config::$serverid
			'pass' => '',
			'db' => 'stk',
		),
		'maintenance' => array(
			'host' => 'localhost',
			'port' => 0,
			'user' => 'root', # when this is empty(), a username is generated from config::$whoami.config::$serverid
			'pass' => '',
			'db' => 'stk',
		),
	);
	static $do_transactions = false;

	static $locale = 'en_US.UTF-8';

	static $baseurl = 'http://localhost/';
	static $static_baseurls = false;

	static $do_image_stat = false;

	// THESE PATHS MUST BE / TERMINATED
	// root inside rootdir for route()-ing, these can be reached from urls
	static $sitedir = 'www/';
	// error dir inside rootdir
	static $errordir = 'errors/';
	// if false, the site will try to re-route an error through the dyn_routes
	static $route_error_status = false;
	// webroot for link generation or whatever
	static $webroot = '/';
	// dir for static content
	static $static_dir = 'static/';
	// fcache dir
	static $fcache_dir = '/tmp/fc/';
	// route cache file
	static $route_cache_path = false;
	// class to load for the stash() of this site
	static $stash_class = 'site_stash';
	// class to load for the session
	static $session_class = 'site_session';
	// default template engine
	static $default_tmpl_type = 'smarty';
	// smarty config
	static $smarty = array(
	);
	// files to inc() before anything serious happens
	static $inc = array(
		'cache-none',
		'site_stash', 
		'site_session', 
		'acl', 
		'input-validation',
		'userlib',
		'util',
		'OAuth',
		'facebook',
	); // load anything you need early (such as the stash class)
	static $disable_mailer = false;
	static $mail_from = 'info@localhost';
	static $mail_backend = 'smtp';//amazon_ses';
	static $mail_backend_params = array(
		'info@localhost'=>array(
		),
	);
	static $mail_bccs = array(
	);

	static $aws_key = '';
	static $aws_secret = '';

	static $img_stores = array(
		'facebook' => array(
			'sizes' => array(
				'user' => array(
					'micro' => 'square',
					'thumb' => 'large',
					'original' => 'large',
				),
			),
		),
		'twitter' => array(
			'sizes' => array(
				'user' => array(
					'micro'=>array(48,48), // normal size, also has bigger size 73x73
				),
			),
		),

		'temp' => array(
			'dir' => '/tmp/',
			'prefix' => 'tempimage-',
		),

		'static' => array(),

		'local' => array(
			'path' => 'files/',
			'hash_len' => 4,
			'sizes' => array(
				// size => array(x, y, should_crop = false)
				'user' => array(
					'thumb' => array(160, 140, true),
					'micro' => array(50, 50, true),
					'featured' => array(150,35,false,array('ondemand'=>true)),
				),
			),
		),
	);

	static $msg_headers = array(
		0 => '<span class="h2">Failure!</span> %s',
		1 => '<span class="h2">Success!</span> %s',
	);

	static $delete_time_limit = 900; // 15 minutes (this is how long the user may delete his own stuff)

	static $bitly = array(
		'login' => '',
		'api_key' => '',
	);
	static $default_session_lifetime = 86400;
	static $persistent_session_lifetime = 2592000; // 30 days

	static $gdata = array(
		'user' => '',
		'pass' => '',
	);
	static $min_pass_length = 6;

	static $norobots = false; // ban the robots
	static $static_files_revision_postfix = '1';
	static $static_rev_via_rewrite = true;
	static $host = 'localhost';
	static $cookie_domain = '.localhost';
	
	static $oauth_services = array(
		'facebook' => array(
			'version' => 2,
			'login_service' => true,
			'register_redirect' => '',
			'login_redirect' => '',
			'key' => '',
			'secret' => '',
			'url_authorize' => 'https://www.facebook.com/dialog/oauth',
			'url_authorize_param' => array(),
			'url_access_token' => 'https://graph.facebook.com/oauth/access_token',
			'permissions' => array(
				'email',
				'user_birthday',
			),
		),
	);

	static $oauth_get_access_token_max_retry_count = 2;

	static $output_filters = array(
	);

	static $db_debug_mode = false;

	static $cli_error_log_file = '/var/log/stk.log';

	static $dyn_routes = array(
	);

	static $message_timeout_ms = 5000;

	static $message_admins_with_debug = false;

	static $session_cookie_name = 'STKSESSID';

	// stomp data
	static $stomp_server_addr = 'tcp://localhost:61613';
	static $stomp_user = 'guest';
	static $stomp_pw = 'guest';
	static $stomp_worker_time = 600;
	static $stomp_max_runs = 300;

	static $cloudfront_api_user = '';
	static $cloudfront_api_secret = '';
	static $cloudfront_distribution_id = '';

        static $cache_kinds = [
                'static' => [
                        'cached_methods' => ['GET', 'HEAD'],
                        'allowed_methods' => ['HEAD', 'GET'],
                        'ttl' => 7200,
                        'headers' => ['Host'],
                        'cookies' => false,
                        'query_string' => false,
                ],
                'cached_dynamic' => [
                        'cached_methods' => ['GET', 'HEAD'],
                        'allowed_methods' => ['HEAD', 'DELETE', 'POST', 'GET', 'OPTIONS', 'PUT', 'PATCH'],
                        'ttl' => 600,
                        'headers' => ['Host', 'Authorization', 'Origin', 'Referer'],
                        'cookies' => true,
                        'query_string' => true,
                ],
                'uncached' => [
                        'cached_methods' => ['GET', 'HEAD'], // FUCF
                        'allowed_methods' => ['HEAD', 'DELETE', 'POST', 'GET', 'OPTIONS', 'PUT', 'PATCH'],
                        'ttl' => 0,
                        'headers' => ['Host', 'Authorization', 'Origin', 'Referer'],
                        'cookies' => true,
                        'query_string' => true,
                ],
        ];

        static $cache_default_kind = 'cached_dynamic';
        static $cache_paths = [
                'static*' => 'static',
                'admin/*' => 'uncached',
        ];
}

// make sure that if this file exists, you dont add it to bzr.
//  it's for checkout-local configuration
if (file_exists('cfg/localcfg.php')) {
	include 'localcfg.php';
}

date_default_timezone_set(config::$timezone);
