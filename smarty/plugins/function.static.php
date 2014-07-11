<?php

function smarty_function_static($params, $sm) {
	if (empty($params['url'])) {
		error('{static url=...} needs url param');
	}
	if (isset($params['compile']) && config::$instance_type=='dev') {
		$purl = url_parse($params['url']);
		array_unshift($purl['path'], $params['compile']);
		array_unshift($purl['path'], 'compile');
		return url($purl);
	}
	return make_static_url($params['url']);
}

