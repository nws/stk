<?php

function smarty_modifier_video_embed($val, $type = 'object', $width=null, $height=null) {
	$settings = array();
	if (!empty($width)) {
		$settings['w'] = $width;
	}
	if (!empty($height)) {
		$settings['h'] = $height;
	}
	return video::get_embed($val, $type, $settings);
}
