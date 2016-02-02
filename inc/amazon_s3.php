<?php

class amazon_s3 {
	public $s3, $bucket;

	function __construct($key,$secret,$bucket) {
		inc('amazon/aws-autoloader');
		$this->s3 = new Aws\S3\S3Client([
			'version' => 'latest',
			'region' => 'us-east-1',
			'credentials' => [
				'key'    => $key,
				'secret' => $secret,
			],
		]);
		$this->bucket = $bucket;
	}
	
	function put_object($filename, $ctype, $content) {
		$opt = array();
		$opt['ACL'] = 'public-read';
		$opt['Body'] = $content;
		$opt['ContentType'] = $ctype;
		$opt['Bucket'] = $this->bucket;
		$opt['Key'] = $filename;
		return $this->s3->putObject($opt);
	}
	
	function get_object($filename, $opt = []) {
		$opt += [
			'Bucket' => $this->bucket,
			'Key' => $filename,
		];
		
		return $this->s3->GetObject($opt);
	}

	function get_object_url($filename, $opt = null) {
		return 'https://s3.amazonaws.com/'.$this->bucket.'/'.$filename;
	}

	function object_exists($filename) {
		$opt = [
			'Bucket' => $this->bucket,
			'Key' => $filename,
		];
		try {
			$res = $this->s3->headObject($opt);
		} catch (Exception $e) {
			return false;
		}
		return true;
	}
	
	function delete_object($filename, $opt = null) { // THIS NEEDS UPDATE XXX
		return $this->s3->delete_object($this->bucket, $filename, $opt);
	}
}

