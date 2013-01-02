<?php
if (!strlen(config::$cookie_domain)
	|| config::$cookie_domain[0] != '.'
	|| strpos(config::$host, substr(config::$cookie_domain, 1)) === false) 
{
	echo "not really\nWARNING: config::\$cookie_domain conflicts config::\$host\n";
	stk_exit();
}

echo "yes it does, ok\n";
