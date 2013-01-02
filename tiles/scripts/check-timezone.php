<?php

if (t::once()) {
	db_connect(); 
}

$ts = time();
$local_time = date('Y-m-d H:i:s', $ts);

$db_time = fi::query("select from_unixtime($ts)")->fetch_col();

if ($db_time !== $local_time) {
	exit(1);
}


