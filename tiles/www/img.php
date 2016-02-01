<?php
t::tmpl('none');
t::undelegate();

inc('s3img');

$img_name = implode('/', t::argv());

redirect(s3img::get_url($img_name));

