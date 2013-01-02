<?php

class stash extends struct {
	protected $args, $page, $url, $location, $roles;
}

function stash() {
	return struct::get('stash');
}


