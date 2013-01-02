<?php

if (t::once()) {
	if (!fi::connect(config::$db['maintenance']['host'], config::$db['maintenance']['user'], config::$db['maintenance']['pass'], config::$db['maintenance']['db'], config::$db['maintenance']['port'])) {
		trigger_error('cannot connect to db', E_USER_ERROR);
		error('cannot connect to db '.fi::error(1));
	}
}

#t::call('scripts/create-shadow', array('show', 'venue', 'artist', 'artist_show', 'show_venue', 'user_show', 'point', 'setlist', 'user'));


if (config::$fcache_dir && !is_dir(config::$fcache_dir)) {
	@mkdir(config::$fcache_dir);
}
