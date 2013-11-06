<?php
//!allow var_dump

function video_url($hash) {
	return url($hash, true);
}

function bbcode_parse($bbcode, $allowimages = false) {
	list($t, $images) = bbcode_parse_extended($bbcode, $allowimages);
	return $t;
}

function bbcode_parse_extended($bbcode,$allowimages=false, $attachimages = false) {
	$bbcode = trim($bbcode);
	if (!$bbcode) {
		return array('', array());
	}
	inc('pear/HTML/BBCodeParser');
	$p = new HTML_BBCodeParser();
	if ($allowimages) {
		$p->addFilter('Images'); // what a penis.
	}
	$p->addFilter('Links'); // the order of these filters is important! (Links can pre-fuck Images's work)

	$tagarray = $p->eqparse_begin($bbcode);

	$images = array();
	if ($allowimages) {
		foreach ($tagarray as &$tag) {
			if (!empty($tag['tag']) and $tag['tag'] == 'img' and $tag['type'] == 1) {
				if (!empty($tag['attributes']) and !empty($tag['attributes']['img'])) {
					$image_url = $tag['attributes']['img'];
					$image_name = strtr($image_url, '/:', '__');
					$images[] = array(
						'url' => $image_url,
						'name' => $image_name,
					);
					$tag['attributes']['img'] = $image_name;
				}
			}
		}
		unset($tag);
		if ($attachimages) {
			$p->eqparse_settagarray($tagarray);
		}
	}

	$t = $p->eqparse_end();

	$t = nl2br($t);

	if ($allowimages && substr_count($t,'[center]')>0 &&  substr_count($t,'[center]')===substr_count($t,'[/center]')) {
		$t = str_replace('[center]','<div class="centered">',$t);
		$t = str_replace('[/center]','</div>',$t);
	}
	return array('<p>'.$t.'</p>', $images);
}

function html2text($html) {
	$text = $html;
	$text = str_replace('<br />',"\n",$text);
	$text = str_replace('>','> ',$text);
	$text = str_replace('</p> ', "\n", $text);
	$text = str_replace('<p> ', '', $text);
	$text = strip_tags($text);
	while (strpos($text,'  ')!==false) {
		$text = str_replace('  ',' ',$text);
	}
	$text = str_replace(array('&apos;','&quot;'),array("'",'"'),$text);
	$text = trim($text);
	return $text;
}

// if headers === null, just continue pushing lines
// might be necessary for huge datasets (pager, keep calling this)
function push_csv($filename, $headers = null, $records) {
	static $hdrs = null;
	$delim = ';';
	$quote = '"';
	$out = fopen('php://output', 'a');

	if ($headers !== null) {
		$filename .= '-'.date('YmdHis').'.csv';
		if (empty($_REQUEST['debug'])) {
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
		}
		else {
			header('Content-Type: text/'.$_REQUEST['debug'].'; charset=UTF-8');
		}
		t::undelegate();
		t::tmpl('none');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array_values($headers), $delim, $quote);
		$hdrs = $headers;
	}

	if ($records === null) {
		$hdrs = null;
		return;
	}
	foreach ($records as $r) {
		fputcsv($out, hash_slice($r, array_keys($hdrs)), $delim, $quote);
	}
}

function push_xls($filename, $headers = null, $records = null) {
	static $wb = null, $ws = null, $bold = null, $row = 0, $hdrs = null;

	inc('pear/Spreadsheet/Excel/Writer');

	if ($headers !== null) {
		$filename .= '-'.date('YmdHis').'.xls';
		header('Content-Type: application/vnd.ms-excel');
		header('Conten-Disposition: attachment; filename="'.$filename.'"');
		t::undelegate();
		t::tmpl('none');

		$wb = new Spreadsheet_Excel_Writer();
		$wb->setVersion(8);

		$bold = $wb->addFormat();
		$bold->setTextWrap();
		$bold->setAlign("top");
		$bold->setBold();

		$wb->send($filename);
		$ws = $wb->addWorksheet($filename);
		$ws->setInputEncoding('UTF-8');
		$ws->writeRow($row++, 0, array_values($headers), $bold);
		$hdrs = $headers;
	}

	if ($records === null) {
		$wb->close();
		$wb = $ws = $bold = $hdrs = null;
		$row = 0;
	}
	else {
		foreach ($records as $r) {
			$ws->writeRow($row++, 0, hash_slice($r, array_keys($hdrs)));
		}
	}
}

function check_admin_token() {
	$admin_token = models::get('user')->get_page_admin_token();

	$f = new Facebook(array(
		'appId'  => config::$oauth_services['facebook']['key'],
		'secret' => config::$oauth_services['facebook']['secret'],
		'cookie' => true, // enable optional cookie support
	));

	try {
		$me = $f->api('/me', 'GET', array('access_token' => $admin_token));
	}
	catch (Exception $e) {
		// token is bad
		debug('check_admin_token(): '.$e);
		return null;
	}
	if (empty($me)) {
		// no such user or wtf
		debug('no user for admin access_token');
		return null;
	}

	return $admin_token;
}

function logto($file, $str) {
	$str = date('[Y-m-d H:i:s] ').$str."\n";
	file_put_contents($file, $str, FILE_APPEND);
}

function html_escape($val) {
	if (is_array($val)) {
		foreach ($val as &$v) {
			$v = html_escape($v);
		}
		unset($v);
	} else {
		$val = htmlentities($val, ENT_QUOTES, 'utf-8');
	}
	return $val;
}

function cleanup_swearwords($text) {
	$patterns = array(
		'bitch',
		'b!tch',
		'bitŠh',
		'b!tŠh',
		'sucker',
		'fucker',
		'motherfucker',
		'm0therfucker',
		'm0ther',
		'mother',
		'fuŠker',
		'fuck',
		'shit',
		'cock',
		'asshole',
		'a$$hole',
		'sucks',
		'$ucks',
		'blow',
		'bl0w',
		'sh!t',
		'@$$ hole',
		'f>ck',
		'fcuk you',
		'cok',
		'coc',
		'c0k',
		'c0c',
		'd!(c)k',
		'fuŠk',
		'Šock',
		'ŠoŠk',
		'fk',
		'fck',
		'cck',
		'd!ck',
		'c0ck',
		'@$$',
		'cunt',
		'cvnt',
		'btch',
		'b!tch',
		'jizz',
		'jizzface',
		'j!z fce',
		'jzfase',
		'cum',
		'giz',
		'kum',
		'dick',
		'pussy',
		'pu$$y',
		'penis',
		'peni$',
		'vagina',
		'bagina',
		'vag!na',
		'reebok', 
		'puma',
		'new balance', 
		'saucony',
		'under armor', 
		'and1', 
		'converse',
		'adidas',
		'nigga',
		'niggaz',
		'nigger',
	);

	foreach ($patterns as &$p) {
		if (preg_match('/^\w.*\w$/', $p)) {
			$p = '/\b'.preg_quote($p, '/').'\b/i';
		}
		else {
			$p = '/'.preg_quote($p, '/').'/i';
		}

	}
	unset($p);

	$replacements = array_fill(0, count($patterns), '*****');

	return preg_replace($patterns, $replacements, $text);
}

// helper to convert numeric arrays to something that array2xml likes
function xelt_array($wrap, $elts) {
	return empty($elts) ? array() : array($wrap => $elts);
}

function array2xml($array, $opts = array(),$path='') {
	static $waka_l = "\x01";
	static $waka_r = "\x02";

	$out = '';
	if (empty($opts) && isset($array['__opts']) && is_array($array['__opts'])) {
		$opts = $array['__opts']; // hackish?
		unset($array['__opts']);
	}
	if (is_array($array)) {
		foreach ($array as $k => $v) {
			if ($v and is_array($v)) {
				$vkeys = array_keys($v);
				$vcount = count($vkeys);
				if ($vkeys[0] === 0 and $vkeys[$vcount-1] === $vcount-1) {
					foreach ($v as $elt) {
						$out .= "$waka_l$k$waka_r";
						$out .= array2xml($elt,$opts,$path.'/'.$k);
						$out .= "$waka_l/$k$waka_r";
					}
				} else {
					$out .= "$waka_l$k$waka_r";
					$out .= array2xml($v,$opts,$path.'/'.$k);
					$out .= "$waka_l/$k$waka_r";
				}
			} else {
				$out .= "$waka_l$k$waka_r";
				if (isset($opts['cdata']) && isset($opts['cdata'][$path.'/'.$k]) && $opts['cdata'][$path.'/'.$k]) {
					$out .= "$waka_l![CDATA[";
				}
				if (isset($opts['strict_values'])) {
					if ($v || $v===0 || $v==='false') {
						$out .= $v;//htmlspecialchars(html_entity_decode($v, ENT_QUOTES, "UTF-8"));
					}
				} else {
					if ($v) $out .= $v;//htmlspecialchars(html_entity_decode($v, ENT_QUOTES, "UTF-8"));
				}
				if (isset($opts['cdata']) && isset($opts['cdata'][$path.'/'.$k]) && $opts['cdata'][$path.'/'.$k]) {
					$out .= "]]$waka_r";
				}
				$out .= "$waka_l/$k$waka_r";
			}
		}
	} else {
		$out .= $array;
	}
	if ($path === '') { // this is not hackish. we are not eskimos.
		$out = htmlspecialchars(html_entity_decode($out, ENT_QUOTES, 'UTF-8'));
		$out = strtr($out, array($waka_l => '<', $waka_r => '>'));
	}
	return $out;
}

function imageurl($value, $size = 'thumb',$type='') {
	static $missing_loc = '/static/img/missing/';
	$url = img::get_url($value, $size);
	if ($url) {
		return $url;
	}
	else {
		if ($type) {
			$type .= '_';
		}
		if (is_array(config::$static_baseurls) and !empty(config::$static_baseurls)) {
			$missing_loc = config::$static_baseurls[0].'static/img/missing/';
		}
		return $missing_loc.$type.$size.'.jpg';
	}
}

function baseurl($rel_url, $base_url = null) {
	if ($base_url === null) {
		$base_url = config::$baseurl;
	}

	if (strpos($rel_url, 'http://') === 0 or strpos($rel_url, 'https://') === 0) {
		return $rel_url;
	}

	return $base_url.$rel_url;
}

function gen_check_char($data) {
	$factor = 2;
	$sum = 0;
	for ($i = strlen($data)-1; $i >= 0; --$i) {
		$n = $factor * hexdec($data[$i]);
		$factor = ($factor == 2 ? 1 : 2);
		$sum += intval($n / 16) + ($n % 16);
	}
	return dechex((16 - ($sum % 16)) % 16);
}

function validate_check_char($data) {
	$factor = 1;
	$sum = 0;
	for ($i = strlen($data)-1; $i >= 0; --$i) {
		$n = $factor * hexdec($data[$i]);
		$factor = ($factor == 2 ? 1 : 2);
		$sum += intval($n / 16) + ($n % 16);
	}
	return ($sum % 16) == 0;
}

function gen_uid() {
	$id = md5(uniqid('', true));
	return $id.gen_check_char($id);
}

function check_uid($uid) {
	if (strlen($uid) != 33) {
		return false;
	}
	return validate_check_char($uid);
}

function make_hash($rec, $id_key, $value_key) {
	$h = array();
	foreach ($rec as $r) {
		$h[ $r[$id_key] ] = $r[$value_key];
	}
	return $h;
}

function flip_record($rec, $id_key) {
	$frec = array();
	foreach ($rec as &$r) {
		$frec[ $r[$id_key] ] = $r;
	}
	unset($r);
	return $frec;
}

function get_ids($rec, $id_key, $permissive = false) {
	$ids = array();
	foreach ($rec as $r) {
		if (isset($r[$id_key])) {
			$ids[] = $r[$id_key];
		} else if (!$permissive) {
			error('get_ids(..., '.$id_key.') failed, key not set in record');
		}   
	}   
	return $ids;
}   

function force_url($str) {
	if (strpos($str, 'http://') === 0 or strpos($str, 'https://') === 0) {
		return $str;
	}
	return "http://$str";
}

function send_mail($to, $type, $mail,$replace_arr=array()) {
	if (!m('kv')->get("mail_{$type}_{$mail}_on")) {
		return;
	}

	if (!($from = m('kv')->get("mail_{$type}_{$mail}_from"))) {
		return;
	}
	if (!($subject = m('kv')->get("mail_{$type}_{$mail}_subject"))) {
		return;
	}
	if (!($body = m('kv')->get("mail_{$type}_{$mail}_body"))) {
		return;
	}
	if (!empty($replace_arr)) {
		foreach ($replace_arr as $r_from=>$r_to) {
			$body = str_replace($r_from,$r_to,$body);
		}
	}

	$body = bbcode_parse($body);

	return m('email')->send($from, $to, array('subject' => $subject, 'body' => $body . m('email')->get_footer() ));
}

function pf_cities() {
	$cities = array(
		'Atlanta, GA' => 'Atlanta, GA',
		'Austin, TX' => 'Austin, TX',
		'Denver, CO' => 'Denver, CO',
		'Houston, TX' => 'Houston, TX',
		'Las Vegas, NV' => 'Las Vegas, NV',
		'Minneapolis, MN' => 'Minneapolis, MN',
		'Myrtle Beach, SC' => 'Myrtle Beach, SC',
		'New Orleans, LA' => 'New Orleans, LA',
		'New York, NY' => 'New York, NY',
		'Philadelphia, PA' => 'Philadelphia, PA',
		'San Diego, CA' => 'San Diego, CA',
		'San Francisco, CA' => 'San Francisco, CA',
		'Scottsdale, AZ' => 'Scottsdale, AZ',
		'Sun Valley, ID (Ketchum)' => 'Sun Valley, ID (Ketchum)',
		'Washington DC/Silver Springs, MD' => 'Washington DC/Silver Springs, MD',
		'West Allis, WI' => 'West Allis, WI',
	);
	return $cities;
}

function pf_art_cities() {
	$cities = array(
		'Atlanta, GA' => 'Atlanta, GA',
		'Austin, TX' => 'Austin, TX',
		'Chicago, IL' => 'Chicago, IL',
		'Denver, CO' => 'Denver, CO',
		'Houston, TX' => 'Houston, TX',
		'Las Vegas, NV' => 'Las Vegas, NV',
		'Los Angeles, CA' => 'Los Angeles, CA',
		'Memphis, TN' => 'Memphis, TN',
		'Minneapolis, MN' => 'Minneapolis, MN',
		'Myrtle Beach, SC' => 'Myrtle Beach, SC',
		'New Orleans, LA' => 'New Orleans, LA',
		'New York, NY' => 'New York, NY',
		'Palm Springs, CA' => 'Palm Springs, CA',
		'Philadelphia, PA' => 'Philadelphia, PA',
		'Portland, OR' => 'Portland, OR',
		'San Diego, CA' => 'San Diego, CA',
		'San Francisco, CA' => 'San Francisco, CA',
		'Scottsdale, AZ' => 'Scottsdale, AZ',
		'Seattle, WA' => 'Seattle, WA',
		'Virginia Beach, VA' => 'Virginia Beach, VA',
		'Washington DC/Silver Springs, MD' => 'Washington DC/Silver Springs, MD',
		'West Allis, WI' => 'West Allis, WI',
	);
	return $cities;
}

function clean_email($email) {
	return preg_replace('/^([^@]+)\+[^@]+@/', '\1@', $email);
}

function hash_email($email) {
	return md5(clean_email($email));
}

function get_email($email_name, $args = array()) {
	$had_subject = t::has('subject');
	if ($had_subject) {
		$old_subject = t::get('subject');
	}

	$body = t::call("email/$email_name", array($args));
	$subject = t::get('subject');

	if ($had_subject) {
		t::def('subject', $old_subject);
	}
	return array(
		'subject' => $subject,
		'body' => $body,
	);
}

function ensure_exclusive_run_flock($lock_file) {
	static $fhs;
	

	if ($lock_file === null) {
		$fhs = array();
		return;
	}

	if (isset($fhs[$lock_file])) {
		// this process has already *successfully* locked this file
		return true;
	}

	if (!touch($lock_file)) { // no running if we cant access the lockfile
		return false;
	}

	@chmod($lock_file, 0666);		// probably for user interact / self locking

	$fh = fopen($lock_file, 'r');
	if (!$fh) { // cannot open lock file, will not run
		return false;
	}

	if (flock($fh, LOCK_EX|LOCK_NB)) {
		// keep this $fh in memory so the lock isnt released until script exit
		$fhs[$lock_file] = $fh;
		return true;
	}

	return false;
}

function background_run($path, $args = array(), $env = array()) {
	$pid = pcntl_fork();
	if ($pid) { // parent or error
		return $pid > 0;
	}

	ensure_exclusive_run_flock(null); // release locks

	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);

	$STDIN = fopen('/dev/null', 'r');
	$STDOUT = fopen('/dev/null', 'w');
	$STDERR = fopen('/dev/null', 'w');

	pcntl_exec($path, $args, $env);
	// if exec died somehow (bad path, etc)
	// kill the child at once!
	// exit would be wrong, since that runs the destructors
	// and that would tear down any c-level resources 
	// we share with the parent we just forked from
	// sending SIGKILL to ourselves is also wrong, 
	// so let's try /bin/false first, if that works, it'll quit properly for us
	pcntl_exec('/bin/false', array(), array());
	posix_kill(posix_getpid(), SIGKILL);
	exit(1) /* EXIT_OK */;
	// FACEPAAAAALM
}


function check_phone_nr($str) {
	$str = trim($str);
	$pats = array(
		'\(\d{3}\)\s*\d{3}\s*-\s*\d{4}',
		'\d{3}\s*-\s*\d{3}\s*-\s*\d{4}',
		'\d{3}\s*\.\s*\d{3}\s*\.\s*\d{4}',
		'\d{10}',
	);
	foreach ($pats as &$p) {
		$p = "(?:$p)";
	}
	unset($p);
	return preg_match('/^(?:'.implode('|', $pats).')$/', $str);
}

function bitly($url) {
	$url = trim($url);
	$r = curl_get('http://api.bit.ly/v3/shorten?format=json&longUrl='.urlencode($url).'&login='.config::$bitly['login'].'&apiKey='.config::$bitly['api_key']);
	$r = json_decode($r);
	if ($r && isset($r->status_code) && $r->status_code == 200 && isset($r->data->url) && $r->data->url) {
		return $r->data->url;
	} else {
		return null;
	}
}

function curl_rq($url, $data = array(), $params = array()) {
	$params += array(
		'connect_timeout' => 20,
		'timeout' => 20,
		'curl_opts' => array(),
		'method' => 'GET',
		'follow_redir' => false,
		'headers' => array(),
	);

	$ch = curl_init();

	debug("curl {$params['method']} $url: ".json_encode($data));

	if ($params['method'] == 'GET') {
		$url .= (strpos($url, '?') === false ? '?' : '&') 
			. http_build_query($data, null, '&');
	}
	else {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $params['method']);
	}

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $params['connect_timeout']);
	curl_setopt($ch, CURLOPT_TIMEOUT, $params['timeout']);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $params['follow_redir']);

	if (!empty($params['headers'])) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $params['headers']);
	}

	if (!empty($params['curl_opts'])) {
		curl_setopt_array($ch, $params['curl_opts']);
	}

	$str = curl_exec($ch);

	if ($str === false) {
		debug("curl Error: ".curl_errno($ch) . " - " . curl_error($ch));
		return null;
	}

	$more_headers = true;
	while (strpos($str, 'HTTP/') === 0 && $more_headers) {
		$more_headers = false;
		list($hdrs, $str) = explode("\r\n\r\n", $str, 2);
		$hdrs = explode("\r\n", $hdrs);
		array_shift($hdrs);
		$headers = array();
		foreach ($hdrs as $l) {
			list($k, $v) = explode(': ', $l);
			$headers[strtr(strtoupper($k), '-', '_')] = $v;
			if ($params['follow_redir'] && $k == 'Location') {
				$more_headers = true;
			}
		}
	}

	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	debug('curl result code = '.$code);

	$decoded_content = null;
	if (isset($params['response_decode'])) {
		switch ($params['response_decode']) {
		case 'json':
			$decoded_content = @json_decode($str, true);
			break;
		}
	}

	return (object)array(
		'status' => $code,
		'headers' => $headers,
		'content' => $str,
		'decoded_content' => $decoded_content,
	);
}

function curl_get($url, $headers = array(),$userpw='', $follow_redir = false, $curl_opts = null, $extended = false) {
	$ch = curl_init();
	if ($extended) {
		curl_setopt($ch, CURLOPT_HEADER, true);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	if ($userpw) {
		curl_setopt($ch, CURLOPT_USERPWD, $userpw);
	}
	if ($follow_redir) {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	}
	if ($curl_opts !== null) {
		curl_setopt_array($ch, $curl_opts);
	}

	if (!preg_grep('/^accept-language:/i', $headers)) {
		$headers[] = 'Accept-Language: en-us';
	}
	if (!empty($headers)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	debug("curl GETing $url");
	$str = curl_exec($ch);

	if (!$str) {
		debug("curl Error: ".curl_errno($ch) . " - " . curl_error($ch));
		return false;
	}

	if ($extended) {
		$more_headers = true;
		while (strpos($str, 'HTTP/') === 0 && $more_headers) {
			$more_headers = false;
			list($hdrs, $str) = explode("\r\n\r\n", $str, 2);
			$hdrs = explode("\r\n", $hdrs);
			array_shift($hdrs);
			$headers = array();
			foreach ($hdrs as $l) {
				list($k, $v) = explode(': ', $l);
				$headers[$k] = $v;
				if ($k == 'Location') {
					$more_headers = true;
				}
			}
		}
		return array(
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => $headers,
			'content' => $str,
		);
	}
	else {
		return $str;
	}
}

function curl_post($url, $data, $headers = array(),$userpw='', $method = 'POST') {
	$ch = curl_init();

	debug("curl POSTing to $url", $data);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	if ($userpw) {
		curl_setopt($ch, CURLOPT_USERPWD, $userpw);
	}

	$headers[] = 'Accept-Language: en-us';
	if (!empty($headers)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	$str = curl_exec($ch);

	if (!$str) {
		debug("curl Error: $url: ".curl_errno($ch) . " - " . curl_error($ch));
		return false;
	}
	
	return $str;
}

function older_than($year, $dob) {
	$dob = strtotime($dob);
	if ($dob === false) {
		return false; // bad format in $dob, fail... :)
	}
	
	$limit = strtotime("-{$year}years");

	return $dob < $limit;
}
function html_unescape($val) {
	if (is_array($val)) {
		foreach ($val as &$v) {
			$v = html_unescape($v);
		}
		unset($v);
	} else {
		if (is_string($val)) {
			$val = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
		}
	}
	return $val;
}
function date_widget_prepare() {
        $y = range(date('Y')+1,1904);
        $years = array_combine($y, $y);
        $m = range(1, 12);
        $months = array();
        foreach ($m as $mon) {
                $months[$mon] = date('M', mktime(12, 0, 0, $mon, 1));
        }
        $d = range(1, 31);
        $days = array();
        foreach ($d as $da) {
                $days[$da] = sprintf('%02d', $da);
        }

        return array(
                $years,
                $months,
                $days,
        );
}

function datetime_widget_prepare_nodefs() {
        $a = datetime_widget_prepare();
        $a[0] = array('Year'=>'Year')+$a[0];
        $a[1] = array('Month'=>'Month')+$a[1];
        $a[2] = array('Day'=>'Day')+$a[2];
        return $a;
}
function datetime_widget_prepare() {
        $date = date_widget_prepare();
        $hours = array();
        for ($i = 0; $i < 24; ++$i) {
                $hr = ($i%12);
                if ($hr == 0) {
                        $hr = 12;
                }
                $pm = ($i >= 12 ? 'pm' : 'am');
                $hours[$i] = $hr.$pm;
        }

        $m = range(0, 59, 15);
        $minutes = array();
        foreach ($m as $mi) {
                $minutes[$mi] = sprintf('%02d', $mi);
        }

        array_push($date, $hours, $minutes);
        return $date;
}

function convert_tz($dt, $to = null, $from = 'UTC', $format = 'Y-m-d H:i:s O') {
	if ($from === null) {
		$from = date_default_timezone_get();
	}
	if ($to === null) {
		$to = date_default_timezone_get();
	}

	if (is_numeric($dt)) {
		$dt = "@$dt";
	}

	$tz_from = new DateTimeZone($from);
	$tz_to = new DateTimeZone($to);

	$d = new DateTime($dt, $tz_from);
	$d->setTimeZone($tz_to);

	return $d->format($format);
}

function t($str) {
	static $copy = null;
	if ($copy === null) {
		$copy = m('copy')->get_all();
	}
	return isset($copy[$str]) ? $copy[$str] : $str;
}

function kv($k) {
	if ($v = m('kv')->get($k)) {
		return $v;
	}
	return $k;
}

function try_to_schedule($user_id, $try_event_ids) {
	m('user')->edit($user_id, array(
		'original_schedule' => $try_event_ids,
	));

	$events = flip_record(m('event')->get_all_by('event_id', $try_event_ids, true), 'event_id');

	$by_timeblocks = array();
	foreach ($try_event_ids as $event_id) {
		if (!isset($events[ $event_id ])) {
			continue;
		}
		$by_timeblocks[ $events[$event_id]['timeblock_id'] ][] = $event_id;
	}

	$result = array();
	foreach ($by_timeblocks as $timeblock_id => $event_ids) {
		$scheduled_one = false;
		foreach ($event_ids as $event_id) {
			$r = false;
			if (!$scheduled_one) {
				$r = m('event')->schedule_event($event_id, $user_id);
			}
			if ($r === true) {
				$scheduled_one = true;
			}
			$result[$event_id] = $r;
		}
	}
	return $result;
}

function fake_email($user_id,$name=false) {
	$user_id *= 12377; // some prime number to slightly obfuscate user ids
	$ret = "xprize-$user_id@xprizevisioneering.com";
	if ($name) {
		$ret = $name.' <'.$ret.'>';
	}
	return $ret;
}

function last_id($array, $idname) {
	$arr = array_slice($array, -1);
	if (!$arr) {
		return null;
	}
	return $arr[0][$idname];
}

function hash_slice($hash, $keys) {
	$ret = array();
	foreach ($keys as $k) {
		$ret[] = isset($hash[$k]) ? $hash[$k] : null;
	}
	return $ret;
}

function pick_hash($hash, $keys) {
	$ret = array();
	foreach ($keys as $k) {
		if (array_key_exists($k, $hash)) {
			$ret[$k] = $hash[$k];
		}
	}
	return $ret;
}

function far_dump() {
	$a = func_get_args();
	ob_start();
	call_user_func_array('var_dump', $a);
	$a = ob_get_clean();
	if (ini_get('xdebug.overload_var_dump')) { //extension_loaded('xdebug')) {
		$a = html_entity_decode(strip_tags($a), ENT_QUOTES, 'UTF-8');
	}
	return $a;
}

function escape_html($s) {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function partition_by(array $array, $cb) {
	$parts = array();
	foreach ($array as $k => $elt) {
		$parts[ $cb($elt) ][$k] = $elt;
	}
	return $parts;
}

function partition_merge(array $partitions) {
	$array = array();
	foreach ($partitions as $partition) {
		foreach ($partition as $k => $v) {
			$array[$k] = $v;
		}
	}
	ksort($array);
	return $array;
}

function ts_find_week_start($ts) {
	$tm = localtime($ts, true);
	while ($tm['tm_wday'] != 1) {
		$ts = mktime($tm['tm_hour'], $tm['tm_min'], $tm['tm_sec'], $tm['tm_mon']+1, $tm['tm_mday']-1, 1900+$tm['tm_year']);
		$tm = localtime($ts, true);
	}
	return $ts;
}

function ts_trunc_to_day($ts) { // truncate timestamp to local timezone's midnight
	$tm = localtime($ts, true);
	return mktime(0, 0, 0, $tm['tm_mon']+1, $tm['tm_mday'], 1900+$tm['tm_year']);
}

function ts_sub($ts, $n, $period) {
	return ts_add($ts, $n, $period, true);
}

function ts_add($ts, $n, $period, $sub = false) {
	$tm = localtime($ts, true);

	if ($sub) {
		$n = -$n;
	}

	switch ($period) {
	case 'year':
		$tm['tm_year'] += $n;
		break;

	case 'week':
		$tm['tm_mday'] += (7*$n);
		break;

	case 'hour':
		$tm['tm_hour'] += $n;
		break;

	case 'day':
		$tm['tm_mday'] += $n;
		break;

	case 'min':
		$tm['tm_min'] += $n;
		break;

	default:
		error("ts_add() cant handle period=$period");
	}


	return mktime($tm['tm_hour'], $tm['tm_min'], $tm['tm_sec'], $tm['tm_mon']+1, $tm['tm_mday'], 1900+$tm['tm_year']);
}

function ts_round_to_day($ts) {
	$ts = ts_add($ts, 12, 'hour');
	return ts_trunc_to_day($ts);
}

function array_flatten($array) {
	$ret = array();
	foreach ($array as $a) {
		$ret = array_merge($ret, is_array($a) ? $a : array($a));
	}
	return $ret;
}

function emit_http_status($status_code) {
	static $codes = array(
		400 => 'Bad Request',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		409 => 'Conflict',
	);

	if (!isset($codes[ $status_code ])) {
		$status_code = 400;
	}

	header($_SERVER['SERVER_PROTOCOL'].' '.$status_code.' '.$codes[$status_code], $status_code);
}

function format_brief_namelist($count, $names, $cutoff = 2) {
	$glue = ' and ';
	$rest = '';
	if ($count > $cutoff) {
		$glue = ', ';
		$rest = ' and '.($count-$cutoff).' more';
	}
	$text = implode($glue, $names).$rest;
	return $text;
}

/* if called with 3 arguments:
 * 	if key does not exist in crit, does nothing
 * 	else: 
 * 		if value of crit[key] is null, WHERE key IS NULL is added to the query
 * 		else: WHERE key = ? is added to the query
 * if called with 4 arguments:
 * 	same as above, but uses the default value if key does not exist in crit
 */
function crit($selq, $key, $crit, $default = null) {
	$use_default = func_num_args() == 4;
	$value = $default;
	if (array_key_exists($key, $crit)) {
		$value = $crit[$key];
	}
	else if (!$use_default) {
		return;
	}
	if ($value === null) {
		$selq->where("$key IS NULL");
	}
	else {
		$selq->where("$key = ?", $value);
	}
}

function autoseo_encode_name($name) {
	return trim(preg_replace('/[^a-z0-9]+/i', '_', $name),'_');
}

function autoseo_get_name($type, $id) {
	$names = m($type)->get_name_mass(array($id));
	if (isset($names[$id])) {
		return autoseo_encode_name($id.'-'.$names[$id]);
	}
}

function autoseo_strip() {
	$tiles = array(
		'journal' => 1,
		'board' => 1,
	);
	$current_tile = preg_replace('@^www/@', '', t::current_tile());
	if (t::argc() && isset($tiles[$current_tile])) {
		$arg = t::argv(0);
		if (($id = intval($arg)) > 0) {
			$name = autoseo_get_name($current_tile, $id);
			if ($name && urlencode($name) !== $arg) {
				$redir = array($current_tile, $name);
				for ($i=1; $i<t::argc();$i++) {
					$redir[]=t::argv($i);
				}
				redirect($redir,301);
			}
			t::set_argv(0, $id);
		}
	}
}

function make_static_url($key) {
	$rev = config::$static_files_revision_postfix;

	if (is_array(config::$static_baseurls) and !empty(config::$static_baseurls)) {
		$sum = array_sum(unpack("c*", $key));
		$staticroot = config::$static_baseurls[$sum%count(config::$static_baseurls)];
	} else {
		$staticroot = config::$webroot;
	}

	$url = $staticroot.$key;
	if ($rev) {
		if (config::$static_rev_via_rewrite) {
			$url = preg_replace('|^'.preg_quote($staticroot, '|').'static/|', "{$staticroot}static_$rev/", $url);
		}
		else {
			$url .= '?n='.$rev;
		}
	}
	return $url;
}

function generate_missing_ondemand_images($loc) {
        if (count($loc) != 6) {
                return;
        }

        list($static_dir, $files_dir,
                $type, $size, $key_dir, $key) = $loc;
        if ("$static_dir/$files_dir" == config::$static_dir.'files') {
                if (!empty(config::$img_stores['local']['sizes'][ $type ][ $size ][3]['ondemand'])) {

                        $key = implode(':', array($type, 'local', $key));
                        $bytes = img::get_bytes($key, $size);
                        if (!$bytes) {
                                db_connect();
                                img::restore_img($key, $type, $size, 'local');
                                $bytes = img::get_bytes($key, $size, true);
                                header('HTTP/1.1 200 OK');
                                header('Content-Type: '.$bytes['meta']['mime']);
                                header('Content-Length: '.$bytes['meta']['size']);
                                echo $bytes['bytes'];
                                stk_exit();
                        }
                }
        }
}

function utc_jsdate($dt) {
	if (!is_numeric($dt)) {
		$dt = strtotime($dt);
	}

	if (!$dt) {
		return '';
	}

	$d = date_create("@$dt");
	date_timezone_set($d, timezone_open('UTC'));
	return date_format($d, 'd M Y H:i:s T');
}

