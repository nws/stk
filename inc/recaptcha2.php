<?php
define('RECAPTCHA2_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');

function recaptcha2_verify($resp, $ip = null) {
	$req = 'secret='.config::$recaptcha['priv_key'];
	$req .= '&response='.urlencode(stripslashes($resp));
	if (!empty($ip)) {
		$req.='&ip='.urlencode(stripslashes($ip));
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, RECAPTCHA2_VERIFY_URL);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec ($ch);

	curl_close ($ch);

	return json_decode($response,1);
}
function recaptcha2_get_head_html() {
	return '<script src="https://www.google.com/recaptcha/api.js"></script>';
}

function recaptcha2_get_html($theme = 'light') {
	$t = " theme=\"$theme\""; 
	return '<div class="g-recaptcha"'.$t.' data-sitekey="'.config::$recaptcha['pub_key'].'"></div>';
}

function resolve_recaptcha_error($st) {
	$arr = array(
		'missing-input-secret' => 'The secret parameter is missing.',
		'invalid-input-secret' => 'The secret parameter is invalid or malformed.',
		'missing-input-response' => 'The response parameter is missing.',
		'invalid-input-response' => 'The response parameter is invalid or malformed.'
	);
	if (isset($arr[$st])) {
		return $arr[$st];
	}
	return null;
}
