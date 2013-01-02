<?php

# redirect(spec) -> redir to spec
# redirect() -> redir to self
# redirect_back() -> go to referer (or '')
#
# editform/whatever flow needs just redirect and redirect_back
# however, anything requiring login or other forced steps:
# page calls:
# redirect_later(spec)
# redirect(spec_for_auth)
# auth page calls:
# redirect_now();
# redirect('');
/* redirect to referer if the referer is set and is us */


function redirect_js($url, $targetframe="top") {
	debug('redirect_js('.$url.', '.$targetframe.')');
	?>
	<!doctype html>
	<html><head>
	<script type="text/javascript">
		window<?=empty($targetframe)?'':'.'.$targetframe ?>.location.href = '<?= $url?>';
	</script>
	</head>
	<body>
	</body>
	</html>
	<?php
	stk_exit(true);
}

function redirect_back($extraparams = array()) {
	check_referer();
	redirect($_SERVER['HTTP_REFERER'] ? '!'.$_SERVER['HTTP_REFERER'] : '',302, $extraparams);
}

/* redirect to url passed, or current url if not */
function redirect($url = null, $status_code = 302, $extraparams = array()) {
	if ($url === null) {
		$url = stash()->location;
	} else {
		$url = url_normalize($url);
	}
	if (!empty($extraparams)) {
		$url['query'] = $extraparams + $url['query'];
	}
	$url = url($url, true);

	header('Location: '.$url, true, $status_code);
	debug('redirect('.$url.')');
	stk_exit(true);
}

/* set up the session so it comes back here (unless we're already in the middle of a flow) */
function redirect_later($url = null, $force = false) {
	if ($url === null) {
		$url = stash()->location;
	} else {
		$url = url_normalize($url);
	}
	if ($force or session()->redirect_later === null) {
		session()->redirect_later = $url;
	}
}

/* redirect to whatever was set up by redirect_later(), unless you pass an url
 * in which case it goes there AND resets redirect_later */
function redirect_now($furl = null) {
	$url = '';
	if (session()->redirect_later !== null) {
		$url = session()->redirect_later;
		session()->redirect_later = null;
	}
	if ($furl !== null) {
		$url = url_normalize($furl);
	}
	redirect($url);
}

function is_redirect_later_available() {
	if (session()->redirect_later !== null) {
		return true;
	} else {
		return false;
	}
}

function check_referer($redirect = true) {
	if (@$_SERVER['HTTP_REFERER']) {
		if ($_SERVER['HTTP_REFERER'][0] == '/' or strpos($_SERVER['HTTP_REFERER'], 'http'.(@$_SERVER['HTTPS'] ? 's' : '').'://'.$_SERVER['HTTP_HOST']) === 0) {
			return true;
		}
	}
	if ($redirect) {
		redirect('');
	} else {
		return false;
	}
}

