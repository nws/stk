<?php
// s3 img upload example
stk_exit(); // so no one calls this by accident

inc('s3img');

if (IS_POST) {
	if (!empty($_FILES['img']['tmp_name'])) {
		list($ok, $name) = s3img::put($_FILES['img']['tmp_name']);
		if ($ok) {
			redirect(s3img::get_url($name));
		} else {
			echo "ERROR: $name";
		}
	}
} 

