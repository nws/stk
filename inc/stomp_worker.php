<?php

inc('Stomp');

function stomp_work($q, $func, $opts=array()) {
	inc('stomp_worker');

	$end_t = time() + config::$stomp_worker_time;
	$timeout_s = min(config::$stomp_worker_time, 60);
	
	$keep_working = isset($opts['keep_working'])? $opts['keep_working'] : false;
	
	$opts += array('read_timeout'=> array($timeout_s,0));

	$stomp = stomp_worker($q, array('auto_ack' => false) + $opts);

	while ($keep_working || time() < $end_t) {
		if (isset( $opts['max_runs'] )) {
			$opts['max_runs']--;
			if (!$opts['max_runs'])
				break;
		}

		$frame = $stomp->read();
		
		if ($frame) {
			$msg = $frame->body['msg'];
			$func($msg);
			$stomp->ack($frame);
		} else if ($keep_working) {
			break;
		}
	}
}

function stomp_worker($qkey, $opts = array()) {
	return new stomp_worker($qkey, $opts);
}

class stomp_worker {
	private $s;
	private $qkey;
	private $worker_id;
	private $auto_ack;

	function __construct($qkey, $opts = array()) {
		$this->s = new Stomp(config::$stomp_server_addr);
		$this->s->connect(config::$stomp_user, config::$stomp_pw);
		$this->qkey = $qkey;
		if (!empty($opts['read_timeout'])) {
			$this->s->setReadTimeout($opts['read_timeout'][0], $opts['read_timeout'][1]);
		}
		if (!empty($opts['worker_id'])) {
			$this->worker_id = $opts['worker_id'];
		}
		else {
			$this->worker_id = posix_getpid();
		}
		if (isset($opts['auto_ack'])) {
			$this->auto_ack = $opts['auto_ack'];
		} else {
			$this->auto_ack = true;
		}

		$this->s->subscribe("/queue/{$this->qkey}");
	}

	function read() {
		$f = $this->s->readFrame();
		if ($f) {
			if ($this->auto_ack) {
				$this->ack($f);
			}
			$data = json_decode($f->body, true);
			$f->body = $data;
		}
		return $f;
	}

	function ack($frame) {
		$this->s->ack($frame->headers['message-id']);
	}

	function reply($frame, $reply) {
		$queue_name = "/queue/{$this->qkey}/results/{$frame->body->client_id}";
		$reply = json_encode(array(
			'worker_id' => $this->worker_id,
			'msg' => $reply,
		));
		return $this->s->send($queue_name, $reply);
	}
}
