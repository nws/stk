<?php

class session_store extends model {
	protected function read($id) {
		$this->used_tables(array('session'));
		$r = sel::from('session')
			->fields('data')
			->where('session_id = ?', $id)
			->exec('data');
		return (string)@$r[0];
	}

	protected function write($id, $data) {
		$this->is_destructive(array('session'));
		if (strlen($id)) {
			$r = fi::query("insert into `session` (`session_id`, `data`) values(?, ?) on duplicate key update `data` = ?", $id, $data, $data);
		}
		return !!$r;
	}

	protected function destroy($id) {
		$this->is_destructive(array('session'));
		if (strlen($id)) {
			$r = mod::delete('session')
				->where('session_id = ?', $id)
				->exec();
		}
		return !!$r;
	}

	protected function gc() {
		$this->is_destructive(array('session'));

		$r1 = mod::delete('session')
			->where('persistent = 0')
			->where('last_modified < date_sub(now(), interval ? second)', config::$default_session_lifetime)
			->exec();

		$r2 = mod::delete('session')
			->where('persistent = 1')
			->where('last_modified < date_sub(now(), interval ? second)', config::$persistent_session_lifetime)
			->exec();

		return ((!!$r1) && (!!$r2));
	}

	protected function set_to_persistent($id) {
		$this->is_destructive(array('session'));
		$r = mod::update('session')
			->values(array('persistent' => 1, '!last_modified' => 'last_modified'))
			->where('session_id = ?', $id)
			->exec();
		return !!$r;
	}

	protected function init_session_transfer($sid, $validation_base = array()) {
		$this->is_destructive(array('session_transfer'));
		$token = uniqid('',true);
		$d = array(
			'session_transfer_id'=>$token,
			'session_id'=>$sid,
			'!created_dt'=>'NOW()',
			'verification_hash'=>md5(json_encode($validation_base)),
		);
		$r = mod::insert('session_transfer')
			->on_duplicate_update()
			->values($d)
			->exec();
		if ($r->error===null) {
			return $token;
		}
	}

	protected function fetch_and_invalidate_session_transfer($token, $validation_base = array()) {
		$this->is_destructive(array('session_transfer'));
		$s = sel::from('session_transfer',sel::NO_ID_TABLE)
			->fields('session_id')
			->where('session_transfer_id = ?',$token)
			->where('verification_hash = ?',md5(json_encode($validation_base)))
			->where('created_dt > DATE_SUB(NOW(), INTERVAL 1 MINUTE)')
			->exec_one();
		if (!empty($s)) {
			$d = mod::delete('session_transfer')
				->where('session_transfer_id = ?',$token)
				->exec();
			return $s['session_id'];
		}
	}
}
