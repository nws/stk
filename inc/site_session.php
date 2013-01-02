<?php

/* THIS is the right place to declare more session variables */
class site_session extends session {
	protected 
		$user_id, # logged in user, or <= 0 if not
		$redirect_later, # grr, but i hope this is the last one
		$pass = array(),
		$oauth = array(); # ???
}
