<?php
//!allow var_dump!
inc('amazon_ses');
$mail = new amazon_ses(null,null,null, config::$aws_key, config::$aws_secret);
var_dump($mail->get_stats());
