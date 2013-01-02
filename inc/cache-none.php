<?php

function cache_fetch($k, &$ret) {
	return false;
}

function cache_store($k, $v) {
	return true;
}

function cache_clear() {
	return true;
}

function cache_delete($k) {
	return true;
}

function cache_add($k, $v) {
	return true;
}

function cache_init() {

}

function cache_clear_maint() {

}
