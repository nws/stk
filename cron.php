#!/usr/bin/php
<?php
if (!isset($argc)) {
	$argc = 2;
	$argv = explode('/',$_SERVER['PATH_INFO']);
}
if ($argc == 1) {
	die("./cron.php <-l|hourly|hour|daily|day|monthly|month|whatever_key_you_desire>\n");
}

$period = $argv[1];
if ($period == '-l') {
	// hahahah hax.
	preg_match_all('/case\s+\'(\w+)\':/', file_get_contents($argv[0]), $m);
	foreach ($m[1] as $p) {
		echo "$p\n";
	}
	exit;
}

chdir(dirname(__FILE__));

$run = array();

switch ($period) {
case 'minute':
	$run = array(
	);
	break;

case 'daily':
case 'day':
	$run = array(
	);
	break;

case 'hourly4':
	$run = array(
	);
	break;

case 'hourly6':
	$run = array(
	);
	break;

case 'hourly':
case 'hour':
	$run = array(
	);
	break;
case 'weekly':
case 'week':
	$run = array(
	);
	break;
case 'monthly':
case 'month':
	break;

default:
	die("unknown period passed to cron.php\n");
}

if (!empty($run)) {
	openlog('stkcron', LOG_PID, LOG_LOCAL7);
	syslog(LOG_INFO, "stkcron($period) starting");
	foreach ($run as $r) {
		if ($r[0]=='!') {
			$r = substr( $r , 1 );
			syslog(LOG_INFO, "stkcron($period): EXEC $r");
			// exec("./run.php $r >/dev/null");
			require_once( 'inc/util.php' );
			background_run( "/usr/bin/php" , array( "./run.php" , $r ) );
		} else {
			syslog(LOG_INFO, "stkcron($period): ./run.php $r");
			exec("./run.php $r");
		}
	}
	syslog(LOG_INFO, "stkcron($period) ending");
} else {
	syslog(LOG_INFO, "stkcron($period) empty, not running anything");
}

exit;

