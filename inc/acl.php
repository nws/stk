<?php

acl::define('guest', null, array(
	acl::deny('pageview'),
	acl::allow('pageview', array(
		'index',
		'oauth/authorize',
		'oauth/callback',
	)),
));

acl::define('user', null, array(
	acl::allow('pageview'),
	acl::deny('useradmin'),
));

acl::define('admin', 'user', array(
	acl::allow('adminpage', array(
		'enter',
	)),
));
