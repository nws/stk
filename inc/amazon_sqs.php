<?php

class amazon_sqs {
	public $sqs, $queue_url, $last_read=null;
	function __construct($key, $secret, $queue_url) {
		inc('amazon/sdk.class');
		inc('amazon/services/sqs.class');
		$this->sqs = new AmazonSQS($key, $secret);
		$this->queue_url = $queue_url;
	}
	
	
	public function get_message_mass($opts=null) {
		$arr = $this->read($opts);
		if (!empty($arr)) {
			$ret = array();
			foreach ($arr as $i=>$v) {
				$ret[$i] = $v['Body'];
			}
			return $ret;
		}
		return false;
	}

	public function get_message($opts=null) {
		unset($opts['MaxNumberOfMessages']);
		$arr = $this->read($opts);
		if (!empty($arr)) {
			return $arr[0]['Body'];
		}
		return false;
	}

	public function remove_mass_all() {
		foreach ($this->last_read as $v) {
			$resp = $this->remove($v['ReceiptHandle']);
		}
		return $resp;
	}

	public function remove_mass_one($n) {
		$resp = $this->remove($this->last_read[$n]['ReceiptHandle']);
		return $resp;
	}

	public function read($opts=null) {
		$resp = $this->sqs->receive_message($this->queue_url, $opts);
		if ($resp->status!=200) {
			debug('SQS ERROR: ',$resp);
		}
		$ret = array();
		$this->last_read = array();
		if (isset($resp->body->ReceiveMessageResult->Message)) {
			foreach ($resp->body->ReceiveMessageResult->Message as $v) {
				$ret[] = json_decode(json_encode($v), TRUE);
			}
			$this->last_read = $ret;
			return $ret;
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
