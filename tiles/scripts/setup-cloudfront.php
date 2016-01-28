<?php
if (!config::$cloudfront_api_user || !config::$cloudfront_api_secret || !config::$cloudfront_distribution_id) {
	echo "Cloudfront settings missing, terminating.\n";
	stk_exit();
}

function make_aws_api_array($arr) {
	return ['Quantity' => count($arr), 'Items'=> $arr];
}

function gen_cache_behaviour($path, $origin_id, $cached_methods, $allowed_methods, $max_ttl, $forward_headers, $forward_cookies, $forward_query_string) {
	if (!$forward_cookies) {
		$cookie_settings = ['Forward' => 'none'];
	} else if (is_array($forward_cookies)) {
		$cookie_settings = ['Forward' => 'whitelist', 'WhitelistedNames' => make_aws_api_array($forward_cookies)];
	} else {
		$cookie_settings = ['Forward' => 'all'];
	}

	$ret = [
		'AllowedMethods' => make_aws_api_array($allowed_methods) + ['CachedMethods' => make_aws_api_array($cached_methods)],
		'MinTTL' => 0,
		'MaxTTL' => $max_ttl,
		'DefaultTTL' => (int)($max_ttl / 2),
		'TrustedSigners' => make_aws_api_array([]) + ['Enabled' => false],
		'SmoothStreaming' => false,
		'ViewerProtocolPolicy' => 'allow-all',
		'TargetOriginId' => $origin_id,
		'ForwardedValues' => [
			'Cookies' => $cookie_settings,
			'Headers' => make_aws_api_array($forward_headers),
			'QueryString' => !!$forward_query_string,
		],
		'Compress' => false,
	];

	if ($path) {
		$ret['PathPattern'] = $path;
	}
	return $ret;
}

function cloudfront_setup() {
	$user = config::$cloudfront_api_user;
	$secret = config::$cloudfront_api_secret;
	$distribution_id = config::$cloudfront_distribution_id;
	
	$env = "AWS_ACCESS_KEY_ID='$user' AWS_SECRET_ACCESS_KEY='$secret'";
	$tool = 'aws';
	$aws_tool_cmd = "$env $tool";

	system("$tool configure set preview.cloudfront true");
	
	$query_opts = "cloudfront get-distribution --id $distribution_id";
	$query_command = "$aws_tool_cmd $query_opts";
	$aws_tool_output = popen($query_command, 'r');
	$distribution_setup = stream_get_contents($aws_tool_output);
	pclose($aws_tool_output);

	$resp = json_decode($distribution_setup, 1);
	$distribution_setup = $resp['Distribution']['DistributionConfig'];
	$etag = $resp['ETag'];

	if (!$distribution_setup) {
		echo "there was a problem downloading the distribution setup. check if the `aws` tool is installed and creditentials are right.\n";
		stk_exit(-1);
	}

	$origin_id = $distribution_setup['Origins']['Items'][0]['Id'];
	echo "using $origin_id as origin for rules.\n";

	$defs = config::$cache_kinds[config::$cache_default_kind];
	$distribution_setup['DefaultCacheBehavior'] = 
		gen_cache_behaviour(null, $origin_id, 
			$defs['cached_methods'], 
			$defs['allowed_methods'], 
			$defs['ttl'], 
			$defs['headers'], 
			$defs['cookies'], 
			$defs['query_string']);

	$behaviors = [];
	foreach (config::$cache_paths as $path => $kind) {
		$defs = config::$cache_kinds[$kind];
		$behaviors[] = gen_cache_behaviour($path, $origin_id, 
				$defs['cached_methods'], 
				$defs['allowed_methods'], 
				$defs['ttl'], 
				$defs['headers'], 
				$defs['cookies'], 
				$defs['query_string']);

	}
	$distribution_setup['CacheBehaviors'] = make_aws_api_array($behaviors);
	
	$config_tmp_file = tempnam(config::$tempdir, 'CF-config-');
	$tmp_h = fopen($config_tmp_file, 'w');
	fwrite($tmp_h, json_encode($distribution_setup));
	fclose($tmp_h);
	echo $config_tmp_file, "\n";
	
	$update_opts = "cloudfront update-distribution --id $distribution_id --distribution-config file://$config_tmp_file --if-match $etag";
	$update_command = "$aws_tool_cmd $update_opts";
	echo "doing: $update_command\n";
	system($update_command);
	unlink($config_tmp_file);
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
