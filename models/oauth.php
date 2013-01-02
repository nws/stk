<?php

class oauth extends model {
	protected function set_access_token($service, $user_id, $token, $secret, $version = 1) {
		$this->is_destructive(array('oauth'));

		return mod::insert('oauth')
			->values(array(
				'version' => $version,
				'user_id' => $user_id,
				'service' => $service,
				'access_token' => $token,
				'access_secret' => $secret,
				'!connected' => 'NOW()',
			))
			->on_duplicate_update()
			->exec();
	}

	protected function set_remote_uid($service, $user_id, $remote_user_id) {
		$this->is_destructive(array('oauth'));

		return mod::update('oauth')
			->values(array('remote_user_id'=>$remote_user_id))
			->where('user_id = ?', $user_id)
			->where('service = ?', $service)
			->exec();
	}

	protected function get_local_remote_userid_pairs_paged($service, $from_uid = 0) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields('user_id', 'remote_user_id', 'username')
			->where('service = ?', $service)
			->where('remote_user_id is not null')
			->order_by('user_id ASC')
			->where('user_id > ?', $from_uid)
			->limit(10000)
			->exec();

		return $r;
	}

	protected function get_local_remote_userid_pairs($service) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields('user_id', 'remote_user_id', 'username')
			->where('service = ?', $service)
			->where('remote_user_id is not null')
			->exec();

		return $r;
	}

	protected function count_connected_users($service) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields()
			->where('service = ?', $service)
			->where('remote_user_id is not null')
			->count();
		return $r;
	}

	protected function get_local_uid_by_remote_uid($service, $remote_user_id) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields('user_id')
			->where('service = ?', $service)
			->where('remote_user_id = ?', $remote_user_id)
			->exec_one();

		return empty($r) ? null : $r['user_id'];
	}

	protected function get_local_uid_by_remote_uid_mass($service, $remote_user_ids) {
		$this->used_tables(array('oauth'));
		if (empty($remote_user_ids)) {
			return array();
		}
		$r = sel::from('oauth')
			->fields('user_id', 'remote_user_id')
			->where('service = ?', $service)
			->where_keys('remote_user_id IN (?)', $remote_user_ids)
			->exec();
		return make_hash($r, 'remote_user_id', 'user_id');
	}

	protected function get_local_uid($service, $field, $remote_user_id) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields('user_id')
			->where('service = ?', $service)
			->where($field.' = ?', $remote_user_id)
			->exec_one();

		return empty($r) ? null : $r['user_id'];
	}

	protected function filter_local_users($service, $remote_user_ids) {
		$this->used_tables(array('oauth'));
		$r = sel::from('oauth')
			->fields('user_id','remote_user_id')
			->where('service = ?', $service)
			->where_keys('remote_user_id in (?)', $remote_user_ids)
			->exec('user_id');

		return $r;
	}

	protected function set_username($service, $user_id, $username, $remote_user_id=null) {
		$this->is_destructive(array('oauth'));

		return mod::update('oauth')
			->values(array('username' => $username,'remote_user_id'=>$remote_user_id))
			->where('user_id = ?', $user_id)
			->where('service = ?', $service)
			->exec();
	}

	protected function remove_access_token($service, $user_id) {
		$this->is_destructive(array('oauth'));

		return mod::delete('oauth')
			->where('user_id = ?', $user_id)
			->where('service = ?', $service)
			->exec();
	}

	protected function get_login_access_tokens($user_id) {
		$this->used_tables(array('oauth'));
		$ret = array();
		foreach (config::$oauth_services as $service => $s) {
			if ($s['login_service']) {
				$ret[$service] = $this->get_access_token($service, $user_id);
			}
		}
		return $ret;
	}

	protected function get_access_token($service, $user_id) {
		$this->used_tables(array('oauth'));

		return sel::from('oauth')
			->fields(
				'version',
				'user_id',
				'service',
				'access_token',
				'access_secret',
				'username',
				'remote_user_id',
				'connected'
			)
			->where('user_id = ?', $user_id)
			->where('service = ?', $service)
			->exec_one();
	}

	protected function get_remote_details($user_id) {
		$this->used_tables(array('oauth'));
		$details = sel::from('oauth')
			->fields(array(
				'service',
				'remote_user_id',
				'username',
			))
			->where('user_id = ?', $user_id)
			->exec();
		return flip_record($details, 'service');
	}

	protected function get_access_for_service_pager($pgvar, $pgcnt, $service, $user_ids = null) {
		$this->used_tables(array('oauth'));
		$users = sel::from('oauth')
			->fields(
				'version',
				'user_id',
				'service',
				'access_token',
				'access_secret',
				'remote_user_id',
				'username',
				'connected'
			)
			->where('service = ?', $service)
			->order_by('user_id ASC');
		if ($user_ids !== null) {
			$users->where_keys('user_id IN (?)', (array)$user_ids);
		}
		$users = $users->pager($pgvar, $pgcnt, $ctrl);

		$this->extra_return($ctrl);
		return $users;
	}

	protected function get_access_token_without_remote_user_id($service) { // lol naming conventions :)
		$this->used_tables(array('oauth'));
		return sel::from('oauth')
			->fields(
				'version',
				'user_id',
				'service',
				'access_token',
				'access_secret',
				'username',
				'connected'
			)
			->where('remote_user_id IS NULL')
			->where('service = ?', $service)
			->exec();
	}

	protected function get_local_user_id($service,$field,$value) {
		$this->used_tables(array('oauth'));
		if (!in_array($field,array('remote_user_id','username'))) {
			return false;
		}
		return sel::from('oauth')
			->fields('user_id')
			->where('service = ?', $service)
			->where($field.' = ?',$value)
			->limit(1)
			->exec_one();
	}
}
