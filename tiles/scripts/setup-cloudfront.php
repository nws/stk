<?php
if (!config::$cloudfront_api_user || !config::$cloudfront_api_secret || !config::$cloudfront_distribution_id) {
	echo "Cloudfront settings missing, terminating.\n";
	stk_exit();
}

function make_aws_api_array($arr) {
	return ['Quantity' => count($arr), 'Items'=> $arr];
}

function gen_cache_behaviour($path, $origin_id, $cached_methods, $allowed_methods, $max_ttl, $forward_headers, $forward_cookies, $forward_query_string) {
	$all_allowed_methods = array_uniq($cached_methods + $allowed_methods);

	if (!$forward_cookies) {
		$cookie_settings = ['Forward' => 'none'];
	} else if (is_array($forwards_cookies)) {
		$cookie_settings = ['Forward' => 'whitelist', 'WhitelistedNames' => make_aws_api_array($forward_cookies)];
	} else {
		$cookie_settings = ['Forward' => 'all'];
	}

	$ret = [
		'AllowedMethods' => make_aws_api_array($all_allowed_methods) + ['CachedMethods' => make_aws_api_array($cached_methods)],
		'MinTTL' => 0,
		'MaxTTL' => $max_ttl,
		'DefaultTTL' => (int)($max_ttl / 2),
		'TrustedSigners' => make_aws_api_array([]) + ['Enabled' => false],
		'SmoothStreaming' => false,
		'ViewerProtocolPolicy' => 'allow-all',
		'TargetOriginId' => $origin_id,
		'ForwardedValues' => [
			'Cookies' => $cookie_settings, ['Forward' => 'all'],
			'Headers' => make_aws_api_array($forward_headers),
			'QueryString' => !!$forward_query_string,
		]
	];

	if ($path) {
		$ret['PathPattern'] = $path;
	}

	return $ret;
}

function gen_default_behavior($origin_id) {
	return gen_cache_behaviour($origin_id, ['GET', 'HEAD'], [''], $max_ttl, $forward_headers, $forward_cookies, $forward_query_string) {
	return [
		'AllowedMethods' => make_aws_api_array(['HEAD', 'DELETE', 'POST', 'GET', 'OPTIONS', 'PUT', 'PATCH']) + ['CachedMethods' => make_aws_api_array(["GET", "HEAD"])],
		'MinTTL' => 0,
		'MaxTTL' => config::$cached_default_max_ttl,
		'DefaultTTL' => (int)(config::$cached_default_max_ttl / 2),
		'TrustedSigners' => make_aws_api_array([]) + ['Enabled' => false],
		'SmoothStreaming' => false,
		'ViewerProtocolPolicy' => 'allow-all',
		'TargetOriginId' => $origin_id,
		'ForwardedValues' => [
			'Cookies' => ['Forward' => 'all'],
			'Headers' => make_aws_api_array(['Authorization', 'Host', 'Origin', 'Referer']),
			'QueryString' => true,
		]
	];
}

function gen_spec_behaviors($origin_id) {
	$ret = [];
	foreach (config::$cache_static_paths as $pathp) {
		$ret[] = [
			'PathPattern' => $pathp,
			'AllowedMethods' => make_aws_api_array(['HEAD', 'GET']) + ['CachedMethods' => make_aws_api_array(["GET", "HEAD"])],
			'MinTTL' => 0,
			'MaxTTL' => config::$cached_static_max_ttl,
			'DefaultTTL' => (int)(config::$cached_static_max_ttl / 2),
			'TrustedSigners' => make_aws_api_array([]) + ['Enabled' => false],
			'SmoothStreaming' => false,
			'ViewerProtocolPolicy' => 'allow-all',
			'TargetOriginId' => $origin_id,
			'ForwardedValues' => [
				'Cookies' => ['Forward' => 'none'],
				'Headers' => make_aws_api_array(['Host']),
				'QueryString' => false,
			]
		];
	}
	return make_aws_api_array($ret);
}

function cloudfront_setup() {
	$user = config::$cloudfront_api_user;
	$secret = config::$cloudfront_api_secret;
	$distribution_id = config::$cloudfront_distribution_id;
	
	$env = "AWS_ACCESS_KEY_ID='$user' AWS_SECRET_ACCESS_KEY='$secret'";
	$tool = '/usr/bin/aws';
	$aws_tool_cmd = "$env $tool";

	system("$tool configure set preview.cloudfront true");
	
	$query_opts = "cloudfront get-distribution --id $distribution_id";
	$query_command = "$aws_tool_cmd $query_opts";
	$aws_tool_output = popen($query_command, 'r');
	$distribution_setup = stream_get_contents($aws_tool_output);
	pclose($aws_tool_output);

	$distribution_setup = json_decode($distribution_setup, 1)['Distribution']['DistributionConfig'];
	var_dump($distribution_setup);

	$origin_id = $distribution_setup['Origins']['Items'][0]['Id'];
	echo "using $origin_id as origin for rules.\n";
	
	$distribution_setup['CacheBehaviors'] = gen_spec_behaviors($origin_id);
	$distribution_setup['DefaultCacheBehavior'] = gen_default_behavior($origin_id);
	
	var_dump($distribution_setup);
}

$args = t::argv();
$op = array_pop($args);
if (!$op) { $op = 'setup'; }

switch ($op) {
	case 'setup': 
		cloudfront_setup();
		break;
	case 'invalidate': break;
	default:
		echo "Unknown operation ".$op."\n";
		stk_exit();
}
