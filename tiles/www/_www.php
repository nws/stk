<?php
if (t::once()) {
	db_connect();
	session_mysql::setup();
}
userlib::bring_up();

t::tmpl('smarty', 'frame');
t::delegate('content');

if (!acl::check('pageview', stash()->url)) {
	set_message('Please Log in');
	redirect('');
}
