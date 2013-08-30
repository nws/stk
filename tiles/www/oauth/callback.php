<?php
t::tmpl('none');

if (!t::argc()) {
	set_message('no service passed', 0);
	redirect_now();
}

$service = t::argv(0);

if (!isset(config::$oauth_services[ $service ])) {
	set_message('invalid service passed', 0);
	redirect_now();
}
$freshconnect = null;

$service_data = config::$oauth_services[ $service ];

inc($service);

$service_class = $service;
if (isset($service_data['service_class'])) {
	$service_class = $service_data['service_class'];
}

if ($service_data['login_service'] !== null && !method_exists($service_class, 'get_username')) {
	set_message('invalid service passed', 0);
	redirect_now();
}

if ($service_data['login_service'] === false and !userlib::is_logged_in()) {
	set_message('Please log in first', 0);
	redirect('user');
}

if ($service_data['login_service'] !== null && !is_bool($service_data['login_service'])) {
	if (!call_user_func(array($service, $service_data['login_service']), 'callback')) {
		set_message('Please log in first');
		redirect('user');
	}
}

$reply = null;
if ($service_data['version'] == 1) {
	if (!isset(session()->oauth['token']) or !isset($_GET['oauth_token']) or $_GET['oauth_token'] != session()->oauth['token']) {
		set_message('bad oauth token', 0);
		redirect_now();
	}

	if (!class_exists($service_data['signature_method_class'])) {
		set_message('invalid service signature class'.$service_data['signature_method_class'], 0);
		redirect_now();
	}

	$consumer = new OAuthConsumer($service_data['key'], $service_data['secret'], null);

	$sign_method = new $service_data['signature_method_class'];

	$temp_token = session()->oauth['token'];
	$temp_secret = session()->oauth['secret'];
	session()->oauth = array();
	session()->session_was_modified();

	$req_token = new OAuthConsumer($temp_token, $temp_secret);

	$param = array('oauth_token' => $temp_token);
	if (isset($service_data['need_oauth_verifier_for_acces_token']) && $service_data['need_oauth_verifier_for_acces_token']) {
		$param['oauth_verifier']=$_GET['oauth_verifier'];
	}
	$req = OAuthRequest::from_consumer_and_token($consumer, null, 'GET', $service_data['url_access_token'], $param);
	$req->sign_request($sign_method, $consumer, $req_token);

	$response = curl_get($req->to_url());

	if (!$response) {
		set_message('no OAuth reply', 0);
		redirect_now();
	}

	debug('RESP', $response);
	parse_str($response, $oauth);

	if (!isset($oauth['oauth_token']) or !isset($oauth['oauth_token_secret'])) {
		set_message('invalid OAuth reply', 0);
		redirect_now();
	}

	$token = $oauth['oauth_token'];
	$secret = $oauth['oauth_token_secret'];
	$expire = null;
}
else if ($service_data['version'] == 2) {
	if (!isset($_GET['code']) or !$_GET['code']) {
		debug('invalid oauth reply', $_GET);
		set_message('invalid oauth reply', 0);
		redirect_now();
	}
	$extra_params = array();
	if (!empty($service_data['url_access_token_param'])) {
		$extra_params = $service_data['url_access_token_param'];
	}
	$response_type = 'query';
	if (!empty($service_data['url_access_token_response_type'])) {
		$response_type = $service_data['url_access_token_response_type'];
	}

	if (!empty($service_data['access_token_post_request'])) {
		$post_request = $service_data['access_token_post_request'];
	} else {
		$post_request = false;
	}

	$reply = OAuth2::get_access_token($service_data['url_access_token'], 
		$service_data['key'], $service_data['secret'], 
		url(stash()->location['path'],true), $_GET['code'], $extra_params, $response_type, $post_request);

	if (!isset($reply['access_token']) or !$reply['access_token']) {
		debug('oauth2 reply', $reply);
		set_message('invalid oauth reply', 0);
		redirect_now();
	}

	$token = $reply['access_token'];
	$secret = null;
	$expire = null;
}
else {
	set_message('invalid service version', 0);
	redirect_back();
}

// got tokens...

$username = $remote_user_id = null;
if ($service_data['login_service'] !== null) {
	// get their remote uid
	$args = array($token);
	if ($secret !== null) {
		$args[] = $secret;
	}
	$u = call_user_func_array(array($service_class, 'get_username'), $args);
	$username = $u['name'];
	$remote_user_id = $u['user_id'];
}

// set the tokens, redirect, etc

if (userlib::is_logged_in()) {
	if (!$remote_user_id) {
		set_message('Could not determine your identity on %s, connect failed', 0, array($service));
		redirect_now();
	}
	else {
		// check if we know this remote uid locally already
		$user_id = models::get('oauth')->get_local_uid_by_remote_uid($service, $remote_user_id);
		if ($user_id && $user_id != stash()->user_id) {
			if (!$username) {
				$username = 'This user';
			}
			set_message($username.' is currently linked to another account. If you would like to link to a different '.$service.' account, please logout of '.$service.'. If you would like to link to '.$username.', please email us',0);
			redirect_now();
		}
	}

	if (!stkoauth::get_token($service)) {
		$freshconnect = true;
	}

	models::get('oauth')->set_access_token($service, stash()->user_id, $token, $secret, $service_data['version']);
	models::get('oauth')->set_username($service, stash()->user_id, $username,$remote_user_id);
	stkoauth::set_session($service, $token, $secret, $remote_user_id);
	if (method_exists($service_class, 'after_oauth_connect')) {
		call_user_func(array($service_class, 'after_oauth_connect'));
	}
}
else if ($service_data['login_service'] and $remote_user_id) { // login_service is a string or true
	$user_id = models::get('oauth')->get_local_uid_by_remote_uid($service, $remote_user_id);

	if ($user_id) { // this user known, log him in
		userlib::login($user_id);
		if (!userlib::is_logged_in()) { // error logging the user in, fail it hard
			debug('error logging the user in, fail it hard');
			if (is_redirect_later_available()) {
				redirect_now();
			}
			redirect('');
		}
		models::get('oauth')->set_access_token($service, $user_id, $token, $secret, $service_data['version']);
		models::get('oauth')->set_username($service, $user_id, $username, $remote_user_id);
		stkoauth::set_session($service, $token, $secret, $remote_user_id);
		set_message('Welcome Back, %s!', null, array(stash()->user['name']));
	}
	else {
		if (!empty($service_data['register_function'])) {
			call_user_func(array($service_class, $service_data['register_function']), $service, $token, $secret, $remote_user_id);
		}
		else {
			// new user. stash his shit in the session and redirect to wherever
			stkoauth::set_session($service, $token, $secret, $remote_user_id);
			redirect($service_data['register_redirect']);
		}
	}
}
else if ($service_data['login_service'] === null) {
	// login_service === null
	m('kv')->set($service.'_oauth_data', $reply);
	if (!empty($reply['access_token'])) {
		m('kv')->set($service.'_access_token', $reply['access_token']);
	}
	else {
		m('kv')->delete($service.'_access_token');
	}
}

if ($freshconnect) {
	$msg = 'Thanks. Your '.ucfirst($service).' account is now linked.';
	if (!empty(session()->oauth['success_msg'])) {
		$msg = sprintf(session()->oauth['success_msg'],ucfirst($service));
		session()->oauth['success_msg'] = null;
		session()->session_was_modified();
	}
	set_message($msg, 1);
}


if (method_exists($service, 'post_authorize')) {
	call_user_func(array($service, 'post_authorize'));
}

if (method_exists($service, 'get_url_after_succesfull_authorize')) {
	$redirurl = call_user_func(array($service, 'get_url_after_succesfull_authorize'), $freshconnect);
	if ($redirurl) {
		redirect($redirurl);
	}
}

redirect_now();

