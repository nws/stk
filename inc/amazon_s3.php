<?php

class amazon_s3 {
	public $s3, $bucket;

	function __construct($key,$secret,$bucket) {
		inc('amazon/sdk.class');
		inc('amazon/services/s3.class');
		$this->s3 = new AmazonS3($key, $secret);
		$this->bucket = $bucket;
	}
	
	function create_object_raw($filename, $ctype, $content) {
		$opt = array();
		$opt['acl'] = AmazonS3::ACL_PUBLIC;
		$opt['body'] = $content;
		$opt['contentType'] = $ctype;
		return $this->s3->create_object($this->bucket, $filename, $opt);
	}
	
	function get_object($filename, $opt = null) {
		return $this->s3->get_object($this->bucket, $filename, $opt);
	}
	
	function delete_object($filename, $opt = null) {
		return $this->s3->delete_object($this->bucket, $filename, $opt);
	}
}

