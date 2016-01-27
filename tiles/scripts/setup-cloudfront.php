<?php
if (!config::$cloudfront_api_user || !config::$cloudfront_api_secret || !config::$cloudfront_distribution_id) {
	echo "Cloudfront settings missing, terminating.\n";
	stk_exit();
}

function cloudfront_setup() {
	$user = config::$cloudfront_api_user;
	$secret = config::$cloudfront_api_secret;
	$distribution_id = config::$cloudfront_distribution_id;
	
	$env = "AWS_ACCESS_KEY_ID='$user' AWS_SECRET_ACCESS_KEY='$secret'";
	$tool = '/usr/bin/aws';
	$aws_tool_cmd = "$env $tool";
	
	$query_opts = "cloudfront get-distribution --id $distribution_id";
	$query_command = "$aws_tool_cmd $query_opts";
	$aws_tool_output = popen($query_command, 'r');
	$distribution_setup = stream_get_contents($aws_tool_output);
	pclose($aws_tool_output);

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
