<?php

class user extends model {
	// if login unknown: return null
	// if pass bad, return false
	// return uid
	protected function get_user_id_by_login_pass($login, $pass) {
		$this->used_tables(array('user'));
		$u = sel::from('user')
			->fields(array(
				'password',
			))
			->where('LOWER(login) = LOWER(?)', $login)
			->exec_one();
		if (!$u || empty($u['password'])) {
			return null;
		}
		if (userlib::check_pass($pass, $u['password'])) {
			return $u['user_id'];
		}
		return false;
	}

	protected function just_logged_in($user_id) {
		$this->is_destructive(array('user'));
		return mod::update('user')
			->values(array('!last_login_dt' => 'NOW()'))
			->where('user_id = ?', $user_id)
			->exec();
	}

	protected function create($rec) {
		$this->is_destructive(array('user'));
		if (isset($rec['password'])) {
			$rec['password'] = userlib::encrypt_pass($rec['password']);
		}

		$rec['!update_dt'] = $rec['!register_dt'] = 'NOW()';

		return mod::insert('user')
			->values($rec)
			->exec();
	}

	protected function get_name_mass($user_ids) {
		$this->used_tables(array('user'));
		$names = sel::from('user')
			->fields('name')
			->where_keys('user_id IN (?)', $user_ids)
			->exec();
		return make_hash($names, 'user_id', 'name');
	}

	protected function edit($user_id, $rec) {
		$this->is_destructive(array('user'));
		$rec['!update_dt'] = 'NOW()';
		if (!empty($rec['password'])) {
			$rec['password'] = userlib::encrypt_pass($rec['password']);
		}
		$rv = mod::update('user')
			->values($rec)
			->where('user_id = ?', $user_id)
			->exec();
		return $rv;
	}

	protected function get_by_user_id($user_id) {
		$this->used_tables(array('user'));
		$rec = sel::from('user')
			->fields(array(
				'user_id',
				'login',
				'name',
				'register_dt',
				'update_dt',
				'last_login_dt',
				'role',
			))
			->where('user_id = ?', $user_id)
			->exec_one();
		return $rec;
	}

	protected function delete($user_id) {
		$this->is_destructive(array('user'));
		return mod::delete('user')
			->where('user_id = ?',$user_id)
			->exec();
	}

	protected function is_valid_user_id($user_id) {
		return sel::from('user')
			->fields()
			->where('user_id = ?', $user_id)
			->count();
	}
}
