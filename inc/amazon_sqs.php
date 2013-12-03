<?php

class amazon_sqs {
	public $sqs, $queue_url, $last_msg=null;
	function __construct($key, $secret, $queue_url) {
		inc('amazon/sdk.class');
		inc('amazon/services/sqs.class');
		$this->sqs = new AmazonSQS($key, $secret);
		$this->queue_url = $queue_url;
	}
	
	
	public function get_message() {
		$arr = $this->read();
		if ($arr) {
			return $arr['Body'];
		}
		return false;
	}
	public function remove_last_message() {
		$resp = $this->remove($this->last_msg['ReceiptHandle']);
		return $resp;
	}

	public function read($opts=null) {
		$resp = $this->sqs->receive_message($this->queue_url, $opts);

		if ($resp->status!=200) {
			debug('SQS ERROR: ',$resp);
		}
		if (isset($resp->body->ReceiveMessageResult->Message)) {
			$arr = json_decode(json_encode($resp->body->ReceiveMessageResult->Message), TRUE);
			$this->last_msg = $arr;
			return $arr;
		}
		return false;
	}
	public function remove($handle, $opts=null) {
		$resp = $this->sqs->delete_message($this->queue_url, $handle ,$opts);
		return $resp;
	}

	public function send($msg) {
		$resp = $this->sqs->send_message($this->queue_url, $msg);
		return $resp;
	}

}
