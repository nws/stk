<?php

function smarty_function_static($params, $sm) {
	if (empty($params['url'])) {
		error('{static url=...} needs url param');
	}
	return make_static_url($params['url']);
}

