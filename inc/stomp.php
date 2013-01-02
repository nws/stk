<?php

/* stomp abstraction from the side of the client */

inc('Stomp');

function _stomp($name, $opts = array()) {
	if (isset( stk_stomp::$instances[$name])) {
		return stk_stomp::$instances[$name];
	}

	$s = new stk_stomp(array(
		'name' => $name,
		'opts' => $opts,
	));

	stk_stomp::$instances[$name] = $s;

	return stk_stomp::$instances[$name];
}

class stk_stomp {
	public static $instances = array();

	private $s;
	private $name;
	private $opts;
	private $client_id;

	private $sent_cnt = 0;
	private $recvd_cnt = 0;

	public function __construct($params) {
		$this->name = $params['name'];
		$this->opts = $params['opts'];
		$this->opts += array(
			'readtimeout_seconds' => 0,
			'readtimeout_usec' => 10000,
		);
		
		if (!empty($_SERVER['SERVER_ADDR'])) {
			$this->client_id = !empty($this->opts['client_id']) ? $this->opts['client_id'] : uniqid($_SERVER['SERVER_ADDR'].'-');
		} else {
			$this->client_id = !empty($this->opts['client_id']) ? $this->opts['client_id'] : uniqid(posix_getpid().'-');
		}   


		try {
			if (class_exists('Stomp')) {
				$this->s = new Stomp(config::$stomp_server_addr);
				$this->s->sync = false;
				debug('new');
				$this->s->connect(config::$stomp_user, config::$stomp_pw);
				debug('connect');
				if (!empty($this->opts['bidi']) && $this->opts['bidi']) {
					$this->s->subscribe($this->_result_queue_name());
					$this->s->setReadTimeout($this->opts['readtimeout_seconds'], $this->opts['readtimeout_usec']);
				}
				debug('constructed');
			} else {
				$this->s = false;
			}
		}
		catch (Exception $e) {
			$this->s = false;
		}
	}
	
	private function _job_queue_name() {
		return "/queue/{$this->name}";
	}

	private function _result_queue_name() {
		return "/queue/{$this->name}_{$this->client_id}";
	}
	
	public function send($msg, $opts = array()) {
		if (!$this->s) {
			return;
		}

		$ttl = 1;
		if (!empty($opts['ttl'])) {
			$ttl = $opts['ttl'];
		}
		else if (!empty($this->opts['ttl'])) {
			$ttl = $this->opts['ttl'];
		}

		$params = array(
			'expires' => (time()+$ttl)*1000,
		);

		if (isset($opts['persistent'])) {
			$params['persistent'] = $opts['persistent'];
		}
		else if (isset($this->opts['persistent'])) {
			$params['persistent'] = $this->opts['persistent'];
		}

		$this->s->send($this->_job_queue_name(), json_encode(array(
			'client_id' => $this->client_id,
			'msg' => $msg,
		)), $params);

		$this->sent_cnt++;
		debug('sent');
	}

	public function recv() {
		if (!$this->s) {
			return;
		}

		if ($this->recvd_cnt >= $this->sent_cnt) {
			return null;
		}
		if (!$this->s->hasFrameToRead()) {
			return null;
		}
		$f = $this->s->readFrame();
		$this->s->ack($f);
		$data = json_decode($f->body);
		$f->body = $data;
		return $f;
		debug('recvd');
	}

}
