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

function find_default_origin($origins) {
	foreach ($origins as $o) {
		if (isset($o['CustomOriginConfig'])) {
			return $o['Id'];
		}
	}
}

function find_s3img_origin($origins) {
	if (empty(config::$img_s3_bucket) || !isset(config::$img_s3_prefix)) { return null; }
	$target_domain = config::$img_s3_bucket;
	$target_path = config::$img_s3_prefix;

	foreach ($origins as $o) {
		if (isset($o['S3OriginConfig'])) {
			if ($o['OriginPath'].'/' == '/'.$target_path && $o['DomainName'] == $target_domain.'.s3.amazonaws.com') {
				return $o['Id'];
			}
		}
	}
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
	$origin_id = find_default_origin($distribution_setup['Origins']['Items']);
	$s3img_origin_id = find_s3img_origin($distribution_setup['Origins']['Items']);
		
	if (!$s3img_origin_id && !empty(config::$img_s3_bucket) && isset(config::$img_s3_prefix)) {
		echo "s3 origin for img not found, making one\n";
		$prefix = trim(config::$img_s3_prefix, '/');
		$id = 'S3-'.config::$img_s3_bucket.'/' . $prefix;

		$s3_origin = [
                        "S3OriginConfig" => [
                            "OriginAccessIdentity" => ""
                        ],
                        "OriginPath" => '/'.$prefix, 
                        "CustomHeaders" => make_aws_api_array([]),
			"Id" => $id,
                        "DomainName"=> config::$img_s3_bucket . ".s3.amazonaws.com",
		];
		$old_origins = $distribution_setup['Origins']['Items'];
		$new_origins = $old_origins;
		$new_origins[] = $s3_origin;
		$distribution_setup['Origins'] = make_aws_api_array($new_origins);
		$s3img_origin_id = $id;
	}

	echo "using $origin_id as origin for rules.\n";
	echo "s3origin: $s3img_origin_id\n";

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

	if ($s3img_origin_id) {
		$defs = config::$cache_kinds['static_s3'];
		$behaviors[] = gen_cache_behaviour('/img/*', $s3img_origin_id, 
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
