<?php

class kv extends model {

	protected function extra_key($method) {
		switch ($method) {
		
		}		
	}
	
	// this is an internal function, used to define defaults for specific keys
	// if your variable has no entry here, it will default to null (if not set)
	function defaults($key) {
		static $defaults = array(
		);
		if (isset($defaults[$key])) {
			return $defaults[$key];
		}
		return null;
	}

	// expiry is null or seconds from now
	protected function set($key, $value) {
		$this->is_destructive(array('kv'));
		$rec = array(
			'kv_id' => $key, 
			'value' => json_encode($value), 
		);
		return mod::replace('kv')
			->values($rec)
			->exec();
	}

	// $default overrides whatever $this->defaults() would return
	protected function get($key, $default = null) {
		$this->used_tables(array('kv'));
		$value = sel::from('kv')
			->fields('value')
			->where('kv_id = ?', $key)
			->exec_one();

		if (!empty($value)) {
			return json_decode($value['value'], true);
		}

		if (func_num_args() == 1) {
			$default = $this->defaults($key);
		}

		return $default;
	}

	protected function mass_get($keys) {
		$this->used_tables(array('kv'));
		$values = sel::from('kv')
			->fields('kv_id', 'value')
			->where_keys('kv_id IN (?)', $keys, true)
			->exec();
		$values = make_hash($values, 'kv_id', 'value');
		$return = array();
		foreach ($keys as $k) {
			if (isset($values[$k])) {
				$return[$k] = json_decode($values[$k], true);
			}
			else {
				$return[$k] = null;
			}
		}
		return $return;
	}

	protected function delete($k) {
		return mod::delete('kv')
			->where('kv_id = ?', $k)
			->exec();
	}
}
