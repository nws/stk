<?php

$GLOBALS['files'] = array(
	'site.sql' => true,
);


/* VERSION CHECK */

echo "checking mysql version ";
$ver = init_db_mysqlcall(config::$db['maintenance']['host'], config::$db['maintenance']['user'], config::$db['maintenance']['pass'], "show variables like 'version';");

if (empty($ver)) {
	echo "ERROR (cannot connect)\n";
	stk_exit(false, 2);
}


list(, $ver) = preg_split('/\s+/', $ver[1], 2);
if (version_compare($ver, '5.0') === -1) {
	echo "ERROR (bad version, needs at least 5.0)\n";
	stk_exit(false, 1);
}

echo "ok\n";

/* DB EXISTENCE CHECK */

echo "checking if db exists ";
$exists = init_db_mysqlcall(config::$db['maintenance']['host'], config::$db['maintenance']['user'], config::$db['maintenance']['pass'], 'show databases like '.escapeshellarg(config::$db['maintenance']['db']).';');

if (count($exists)) { // we already have a db, check schema
	echo "ok\n";
	echo "checking schema against db\n";
	$tempdb = substr(uniqid(config::$db['maintenance']['db']), 0, 16);
	init_db_mysqlcall(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		'create database '.$tempdb.' default charset \'utf8\';'
	);
	init_db_init_db(	
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		$tempdb,
		false
	);

	$orig_chk = init_db_schema_checksum(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		config::$db['maintenance']['db']
	);
	$temp_chk =  init_db_schema_checksum(	
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		$tempdb
	);
	
	$orig_indx = init_db_indexes(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		config::$db['maintenance']['db']);
	$temp_indx = init_db_indexes(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		$tempdb);
	$exit = false;

	$rv = init_db_calc_diff($temp_chk, $orig_chk,$temp_indx,$orig_indx);

	if ($rv) {
		echo "ERROR, check your db, back up what you need, drop the db and re-run ./check.sh\n";
		echo $rv;
		$exit = true;
	} else {
		echo "ok\n";
	}

	init_db_mysqlcall(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		'drop database '.$tempdb.';'
	);

	if ($exit) {
		stk_exit(false, 3);
	}

} else { // just create it
	echo "no\ncreating db ";
	init_db_mysqlcall(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		'create database '.config::$db['maintenance']['db'].' default charset \'utf8\';'
	);
	echo "ok\ncreating tables: ";
	init_db_init_db(
		config::$db['maintenance']['host'],
		config::$db['maintenance']['user'],
		config::$db['maintenance']['pass'], 
		config::$db['maintenance']['db'], 
		true
	);
	echo "created tables\n";
}

function init_db_init_db($host, $user, $pass, $db, $data_as_well = false) {
	global $files;
	$sqldir = 'sql/';
	// true: schema
	// false: just data
	foreach ($files as $f => $type) {
		if (!$data_as_well and !$type) {
			continue;
		}
		echo " importing $f\n";
		init_db_mysqlinsert(
			$host,
			$user,
			$pass,
			$db,
			$sqldir.$f
		);
	}
}

function init_db_mysqlcall($host, $user, $pass, $cmds) {
	$cmd = 'echo %s | mysql -h %s -u %s --password=%s';
	$args = array();
	foreach (array($cmds, $host, $user, $pass) as $a) {
		$args[] = escapeshellarg($a);
	}
	$cmd = vsprintf($cmd, $args);
#	echo "$cmd\n";
	$ret = array();
	exec($cmd, $ret);
	return $ret;
}

function init_db_mysqlinsert($host, $user, $pass, $db, $file) {
	$cmd = 'mysql -h %s -u %s --password=%s %s < %s';
	$args = array();
	foreach (array(
		$host,
		$user,
		$pass,
		$db,
		$file,
	) as $a)
   	{
		$args[] = escapeshellarg($a);
	}
	$cmd = vsprintf($cmd, $args);
#	echo "$cmd\n";
	$ret = array();
	exec($cmd, $ret);
	return $ret;
}

function init_db_indexes($host,$user,$pass,$db) {
	$tbls = init_db_mysqlcall($host, $user, $pass, "USE $db; SHOW TABLES;");
	array_shift($tbls);
	$diff = array();
	$indexes = array();
	foreach ($tbls as $t) {
		if ($t[0] == '_') { // skipping shadow-tables and anything else that looks like it
			continue;
		}
		$cmd = "USE $db; SHOW INDEXES FROM `$t`;";
		$descr = init_db_mysqlcall($host, $user, $pass, $cmd);
		$indexes[$t] = $descr;
	}
	return $indexes;
}

function init_db_schema_checksum($host, $user, $pass, $db) {
	$tbls = init_db_mysqlcall($host, $user, $pass, "USE $db; SHOW TABLES;");
	array_shift($tbls);
	$diff = array();
	foreach ($tbls as $t) {
		if ($t[0] == '_') { // skipping shadow-tables and anything else that looks like it
			continue;
		}
		$cmd = "USE $db; DESCRIBE `$t`;";
		$descr = init_db_mysqlcall($host, $user, $pass, $cmd);
		$diff[$t] = $descr;
	}

	return $diff;
}

function init_db_calc_diff($temp, $orig, $tidx, $oidx) {
	$ktdiff = array_diff_key($temp, $orig);
	$kodiff = array_diff_key($orig, $temp);
	
	$ret = '';
	if (!empty($ktdiff)) {
		return 'ERROR, you have missing tables: '.implode(', ',array_keys($ktdiff))."\n";
	}
	if (!empty($kodiff)) {
		$ret.= 'WARNING, you have extra tables: '.implode(', ',array_keys($kodiff))."\n";
		foreach (array_keys($kodiff) as $t) {
			unset($orig[$t]);
		}
	}

	foreach (array_keys($orig) as $t) {
		$tdiff = array_diff($temp[$t], $orig[$t]);
		$odiff = array_diff($orig[$t], $temp[$t]);
		if (!empty($tdiff)) {
			$parsed = init_db_parse_diff($t, $tdiff, true);
			$ret .= "ERROR: you have missing fields in $t: \n  ".implode("\n  ", $tdiff)."\n";
			$ret .= "ERROR: suggested queries to fix it:\n".implode("\n", $parsed)."\n";
		}
		if (!empty($odiff)) {
			$parsed = init_db_parse_diff($t, $odiff, false);
			$ret .= "WARNING: you have extra fields in $t: \n  ".implode("\n  ", $odiff)."\n";
			$ret .= "WARNING: suggested queries to fix it:\n".implode("\n", $parsed)."\n";
		}
		$ret .= init_db_diff_indexes($t,$tidx[$t],$oidx[$t]);
	}
	return $ret;
}

function init_db_parse_diff($table, $diff, $add = true) {
	$sql = array();
	foreach ($diff as $d) {
		list($name, $type, $is_null, $key, $default) = explode("\t", $d);
		$s = '/* ERROR */  ALTER TABLE `'.$table.'` ';
		if ($add) {
			$s .= 'ADD `'.$name.'` '.$type.' '.($is_null == 'NO' ? 'NOT NULL' : 'NULL').($default != "NULL" ? ' DEFAULT \''.$default.'\'' : '').';';
		} else {
			$s .= 'DROP `'.$name.'`;';
		}
		$sql[] = $s;
		if ($add and $key) {
			if ($key == 'UNI') {
				$sql[] = '/* ERROR */  ALTER TABLE `'.$table.'` ADD UNIQUE(`'.$name.'`);';
			} else {
				$sql[] = 'ERROR:  -- THE INDEXES HAVE CHANGED ON '.$table.'.'.$name;
			}
		}
	}
	return $sql;
}

function init_db_diff_indexes($table,$tidx,$oidx) { // TODO: better PRIMARY handling
	$ret = '';
	
	array_shift($tidx);
	$ti = array();
	$tu = array();
	foreach ($tidx as $idx) {
		$index = explode("\t",$idx);
		if ($index[1]) {
			$ti[$index[2]][$index[3]] = $index[4];
		} else {
			$tu[$index[2]][$index[3]] = $index[4];
		}
		
	}
	array_shift($oidx);
	$oi = array();
	$ou = array();
	foreach ($oidx as $idx) {
		$index = explode("\t",$idx);
		if ($index[1]) {
			$oi[$index[2]][$index[3]] = $index[4];
		} else {
			$ou[$index[2]][$index[3]] = $index[4];
		}
		
	}
	$tu = _anonymize_indexes($tu);
	$ou = _anonymize_indexes($ou);

	$ti = _anonymize_indexes($ti);
	$oi = _anonymize_indexes($oi);

	$new_indexes = @array_diff_assoc($ti,$oi);
	$new_uniques = @array_diff($tu,$ou);
	foreach ($new_indexes as $iname => $ni) {
		$ret .= init_db_create_index($table,$iname,$ni,0);
		unset($ti[$iname]);
	}
	foreach ($new_uniques as $iname => $ni) {
		if($iname == 'PRIMARY') {
			$ret .= "ERROR: NEW PRIMARY KEY ON $table!";
		} else {
			$ret .= init_db_create_index($table,$iname,$ni,1);
			unset($tu[$iname]);
		}
	}
	foreach ($ti as $iname => $fields) {
		if (implode(',',$fields) == implode(',',$oi[$iname])) {
			continue;
		}
			
		$ret .="/* ERROR */ DROP INDEX `$iname` ON `$table`;\n";
		$ret .= init_db_create_index($table,$iname,$fields,0);
	}
	foreach ($tu as $iname => $fields) {
		if (isset($ou[$iname]) and implode(',',$fields) == implode(',',$ou[$iname])) {
			continue;
		}
		
		if ($iname == 'PRIMARY') {
			$ret .= "ERROR: PRIMARY KEY CHANGED ON $table!";
		} else {
			$ret .="/* ERROR */ DROP INDEX `$iname` ON `$table`;\n";
			$ret .= init_db_create_index($table,$iname,$fields,1);
		}
	}
	return $ret;
}

function _anonymize_indexes($index_array) {
	foreach ($index_array as $k=>$v) {
		if ($k == 'PRIMARY') {
			continue;
		}
		$new_key = '_anon_index_'.implode('_',$v);
		if (isset($index_array[$new_key])) {
			echo "WAT?\n";
		}
		$index_array[$new_key] = $v;
		unset($index_array[$k]);
	}
	return $index_array;
}

function init_db_create_index($table,$name,$fields,$uniq) {
	if (strpos($name,'_anon_index_')===0) {
		$name = '';
	} else {
		$name = '`'.$name.'`';
	}
	return "/* ERROR */ ALTER TABLE `$table` ADD ".($uniq?'UNIQUE ':'').'INDEX '.$name.' (`'.implode('`, `',$fields).'`);'."\n";
}
