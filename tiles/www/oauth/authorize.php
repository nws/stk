<?php

t::tmpl('none');

if (!t::argc()) {
	set_message('no service passed');
	redirect_back();
}

$service = t::argv(0);

if (!isset(config::$oauth_services[ $service ])) {
	set_message('invalid service passed');
	redirect_back();
}

$service_data = config::$oauth_services[ $service ];

if ($service_data['login_service'] === false and !userlib::is_logged_in()) {
	set_message('Please log in first');
	redirect('user');
}

if (!is_bool($service_data['login_service'])) {
	inc($service);
	if (!call_user_func(array($service, $service_data['login_service']), 'authorize')) {
		set_message('Please log in first');
		redirect('user');
	}
}

if ($service_data['version'] == 1) {
	if (!class_exists($service_data['signature_method_class'])) {
		set_message('invalid service signature class'.$service_data['signature_method_class']);
		redirect_back();
	}

	$consumer = new OAuthConsumer($service_data['key'], $service_data['secret'], null);

	$sign_method = new $service_data['signature_method_class'];

	$param = null;
	if (isset($service_data['need_callback_in_req_token']) && $service_data['need_callback_in_req_token']) {
		$redirect_uri = stash()->location['path'];
		$redirect_uri[count($redirect_uri)-2] = 'callback';

		$redirect_uri = url($redirect_uri, true);
		$param = array('oauth_callback'=>$redirect_uri);
	}

	$req = OAuthRequest::from_consumer_and_token($consumer, null, 'GET', $service_data['url_req_token'],$param);
	$req->sign_request($sign_method, $consumer, null);

	$response = curl_get($req->to_url());

	if (!$response) {
		set_message('no OAuth reply');
		redirect_back();
	}

	parse_str($response, $oauth);

	if (!isset($oauth['oauth_token']) or !isset($oauth['oauth_token_secret'])) {
		set_message('invalid OAuth reply');
		redirect_back();
	}

	session()->oauth['secret'] = $oauth['oauth_token_secret'];
	session()->oauth['token'] = $oauth['oauth_token'];
	session()->session_was_modified();

	$auth_url = $service_data['url_authorize'].'?oauth_token='.$oauth['oauth_token'];
}
else if ($service_data['version'] == 2) {
	$permissions = null;
	if (isset($service_data['permissions'])) {
		$permissions = $service_data['permissions'];
	}

	$params = array();
	if (!empty($service_data['url_authorize_param'])) {
		$params = $service_data['url_authorize_param'];
	}
	$op = session()->oauth;
	if (!empty($op['url_authorize_param']) && is_array($op['url_authorize_param'])) {
		$params = array_merge($params,$op['url_authorize_param']);
		session()->oauth['url_authorize_param'] = null;
		session()->session_was_modified();
	}
	if (!empty($op['extra_permissions']) && is_array($op['extra_permissions'])) {
		$permissions = array_merge($permissions,$op['extra_permissions']);
		session()->oauth['extra_permissions'] = null;
		session()->session_was_modified();
	}


	$redirect_uri = stash()->location['path'];
	$redirect_uri[count($redirect_uri)-2] = 'callback';

	$redirect_uri = url($redirect_uri, true);
	$auth_url = OAuth2::authorize_url($service_data['url_authorize'], 
		$service_data['key'], $redirect_uri, $permissions, $params);
}
else {
	set_message('invalid service version');
	redirect_back();
}

if (!empty($service_data['login_redirect'])) {
	redirect_later($service_data['login_redirect']);
}
else {
	redirect_later(stk_referer(false));
}

redirect($auth_url);
