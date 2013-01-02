<?php

function smarty_modifier_video_thumb($val, $size = 'default') {
	return video::get_thumb($val, $size);
}
