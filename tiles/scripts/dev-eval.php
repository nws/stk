<?php
db_connect();
foreach (t::argv() as $e) {
	eval($e);
}
