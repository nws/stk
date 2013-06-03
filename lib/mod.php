<?php

class mod {
	public $debug_mode = false;

	private $options;

	private $mode;
	private $where = array();
	private $where_args = array();
	private $table;
	private $values;
	private $limit;
	private $completed_queries = array();
	private $args = array();
	private $order_by;

	private $on_duplicate_update = null;

	private $update_external = array();

	private $has_run = false;

	private $warn_on_duplicate_key = true;

	private static $in_transaction = false;
	private static $should_start_transaction = false;
	private static $exit_handler_registered = false;

	public function __construct($mode, $table, $options = array()) {
		$this->mode = $mode;
		$this->table = $table;
		$this->options = $options;
	}
	static function update($table, $options = array()) {
		return new self('update', $table, $options);
	}
	static function insert($table, $options = array()) {
		return new self('insert', $table, $options);
	}
	static function insert_ignore($table, $options = array()) {
		return new self('insert ignore', $table, $options);
	}
	static function delete($table, $options = array()) {
		return new self('delete', $table, $options);
	}
	static function replace($table, $options = array()) {
		return new self('replace', $table, $options);
	}
	// can be array of values to update
	// or true, in which case we reuse the insert's values
	public function on_duplicate_update($update_values = true) {
		$this->on_duplicate_update = $update_values;
		return $this;
	}

	public function silence_duplicate_key_warnings() {
		$this->warn_on_duplicate_key = false;
		return $this;
	}

	public function debug($set = true) {
		$this->debug_mode = $set;
		return $this;
	}

	public function values($values) {
		if (empty($values)) {
			trigger_error('mod::'.$this->mode.'->values called with empty $values', E_USER_ERROR);
		}

		static $warn_fieldspecs = array(
			'#' => true,
			'*' => true,
		);
		foreach ($values as $fld => $v) {
			if (isset($warn_fieldspecs[$fld[0]])) {
				trigger_error('mod::'.$this->mode.'->values called with complex fieldspec '.$fld.', aborting', E_USER_ERROR);
			}

			if (strpos($fld, '/') !== false) {
				unset($values[$fld]);

				list($field, $table) = explode('/', $fld);
				if (!isset($this->update_external[$table])) {
					$this->update_external[$table] = array();
				}
				$this->update_external[$table][$field] = $v;
			}
		}
		$this->values = array($values);
		return $this;
	}

	public function multi_values($mvalues) {
		if (empty($mvalues)) {
			trigger_error('mod::'.$this->mode.'->multi_values called with empty $values', E_USER_ERROR);
		}
		static $warn_fieldspecs = array(
			'#' => true,
			'*' => true,
		);
		$first_keys = null;
		$this->values = array();
		foreach ($mvalues as $values) {
			$keys = array_keys($values);
			sort($keys);
			$keys = implode("\x1c", $keys);
			if ($first_keys === null) {
				$first_keys = $keys;
			}
			if ($first_keys !== $keys) {
				trigger_error('mod::'.$this->mode.'->multi_values called with different hashes', E_USER_ERROR);
			}
			foreach ($values as $fld => $v) {
				if (isset($warn_fieldspecs[$fld[0]])) {
					trigger_error('mod::'.$this->mode.'->multi_values called with complex fieldspec '.$fld.', aborting', E_USER_ERROR);
				}

				if (strpos($fld, '/') !== false) {
					trigger_error('mod::'.$this->mode.'->multi_values called with external field update '.$fld.', aborting', E_USER_ERROR);
				}
			}
			$this->values[] = $values;
		}
		return $this;
	}

	public function limit($n) {
		$this->limit = intval($n);
		return $this;
	}

	public function where($where) {
		$where = func_get_args();
		call_args_flatten($where);
		$str = array_shift($where);

		if (!empty($where)) {
			$this->where_args = array_merge($this->where_args, $where);
		}
		$this->where[] = $str;
		return $this;
	}

	public function where_keys($snippet, $ids) {
		if (empty($ids) or !is_array($ids)) {
			error('mod::where_keys called with no ids, snippet: ('.$snippet.')');
		}
		foreach ($ids as &$i) {
			$i = @intval($i);
		}
		unset($i);
		$snippet = str_replace('(?)', '('.implode(', ', $ids).')', $snippet);
		$this->where[] = $snippet;
		return $this;
	}

	public function order_by($what) {
		$this->order_by = $what;

		return $this;
	}

	private function _check() {
		if (!isset($this->table)) {
			error('mod::'.$this->mode.' called without table');
		} 
		else if ($this->mode == 'insert' and count($this->where)) {
			trigger_error('mod::insert with where', E_USER_ERROR);
		}
	   	else if ($this->mode != 'insert' and $this->mode != 'insert ignore' and $this->mode != 'replace' and !count($this->where)) {
			trigger_error('mod::'.$this->mode.' without where', E_USER_ERROR);
		}
	   	else if ($this->mode != 'delete' and ($this->values === null or !is_array($this->values))) {
			trigger_error('mod::'.$this->mode.' called without any values, no point in life, giving up', E_USER_ERROR);
		}
	   	else if ($this->mode == 'delete' and $this->values !== null) {
			trigger_error('mod::delete called with a values. what?', E_USER_ERROR);
		}
	   	else if ($this->mode == 'insert' and $this->limit !== null) {
			trigger_error('mod::insert called with a LIMIT', E_USER_ERROR);
		}
	   	else if ($this->mode != 'update' and !empty($this->update_external)) {
			trigger_error('mod::'.$this->mode.' called with complex fieldnames', E_USER_ERROR);
		}
		else if ($this->mode != 'update' and $this->order_by !== null) {
			trigger_error('mod::'.$this->mode.' called with order_by', E_USER_ERROR);
		}
		else if ($this->mode != 'insert' and $this->on_duplicate_update !== null) {
			trigger_error('mod::'.$this->mode.' called with on_duplicate_update', E_USER_ERROR);
		}
		else if ($this->mode == 'update' && count($this->values) > 1) {
			trigger_error('mod::'.$this->mode.' called with multiple values', E_USER_ERROR);
		}
		else if ($this->mode == 'insert' && $this->on_duplicate_update !== null && count($this->values) > 1) {
			//trigger_error('mod::'.$this->mode.' called with multiple values and on_duplicate_update', E_USER_ERROR);
		}
	}

	private function to_sql_set($values) {
		$args = $set = array();
		foreach ($values as $fld => $val) {
			if ($fld[0] == '!') {
				$fld = substr($fld, 1);
				$set[] = "`$fld` = $val";
			}
			else {
				$set[] = "`$fld` = ?";
				$args[] = $val;
			}
		}
		$set = implode(',', $set);

		return array($set, $args);
	}

	/*
	 * insert into table set f=v,f2=v2
	 * update table set f=v,f2=v2 where quux = 1
	 * delete from table where quuz = 2
	 */
	public function to_s() {
		if (!empty($this->completed_queries)) {
			return $this->completed_queries;
		}

		static $statements = array(
			'insert' => 'INSERT INTO `%s`',
			'insert ignore' => 'INSERT IGNORE INTO `%s`',
			'update' => 'UPDATE `%s`',
			'delete' => 'DELETE FROM `%s`',
			'replace' => 'REPLACE INTO `%s`',
		);

		$this->_check();

		$values_count = count($this->values);
		$values_start = 0;
		$values_batch = 100;

		do {
			$q = sprintf($statements[$this->mode], $this->table);

			$args = array();

			$set = ''; // fuck dynamic scope
			if ($values_count > 0) {
				if ($this->mode == 'update' || $values_count == 1) {
					list($set, $_args) = $this->to_sql_set($this->values[0]);
					$q .= "SET $set";
					$args = array_merge($args, $_args);
					$values_start = 1; // special case, we'll exit at the bottom now
				}
				else {
					$names = $all_values = array();
					$collect_names = true;
					for ($i = 0; $i < $values_batch && $i+$values_start < $values_count; ++$i) {
						$vals = $this->values[$i+$values_start];
						$set = array();
						foreach ($vals as $fld => $val) {
							if ($fld[0] == '!') {
								$fld = substr($fld, 1);
								$set[] = $val;
							}
							else {
								$set[] = '?';
								$args[] = $val;
							}
							if ($collect_names) {
								$names[] = "`$fld`";
							}
						}
						$collect_names = false;
						$all_values[] = '('.implode(',', $set).')';
					}
					$values_start += $i;
					$names = implode(', ', $names);
					$q .= ' ('.$names.') VALUES'.implode(',', $all_values);
				}
			}

			$main_where = null;
			if (!empty($this->where)) {
				$args = array_merge($args, $this->where_args);
				$where = array();
				foreach ($this->where as $w) {
					$where[] = '('.$w.')';
				}
				$main_where = ' WHERE '.implode(' AND ', $where);
				$q .= $main_where;
			}

			if ($this->order_by !== null) {
				$q .= ' ORDER BY '.$this->order_by;
			}

			if ($this->limit !== null) {
				$q .= ' LIMIT '.$this->limit;
			}

			if ($this->on_duplicate_update !== null) {

				if (is_array($this->on_duplicate_update)) {
					list($_set, $_args) = $this->to_sql_set($this->on_duplicate_update);
				}
				else {
					$_set = $set;
					$_args = $args;
				}
				$q .= ' ON DUPLICATE KEY UPDATE '.$_set;
				$args = array_merge($args, $_args);
			}

			$this->completed_queries[] = $q;
			$this->args[] = $args;

		} while ($values_count > $values_start);

		// update $table set $set where $table_id = (select table_id from $this->table where org_where)
		if (!empty($this->update_external)) {
			foreach ($this->update_external as $table => $values) {
				$set = array();
				$args = array();
				foreach ($values as $fld => $v) {
					if ($fld[0] == '!') {
						$fld = substr($fld, 1);
						$set[] = "`$fld` = $v";

					} else { 
						$set[] = "`$fld` = ?";
						$args[] = $v;
					}
				}
				$args = array_merge($args, $this->where_args);
				$set = implode(', ', $set);
				$q = 'UPDATE `'.$table.'` SET '.$set.' WHERE `'.$table.'_id` = (SELECT `'.$table.'_id` FROM `'.$this->table.'`'.$main_where.')';
				$this->completed_queries[] = $q;
				$this->args[] = $args;
			}
		}
		return array($this->completed_queries, $this->args);
	}

	public function exec() {
		$rv = $this->real_exec();
		$this->has_run = true;
		return $rv;
	}

	private function real_exec() {
		$this->to_s();
		$this->_real_start_transaction();
		foreach ($this->completed_queries as $idx => $q) {
			$args = &$this->args[$idx];
			if ($this->debug_mode) {
				debug('mod::exec', $q, $args);
			}
			$old_warn_flag = fi::$warn_on_duplicate_key;
			fi::$warn_on_duplicate_key = $this->warn_on_duplicate_key;
			$q = array(
				'query' => $q,
				'args' => $args,
			);

			if (isset($this->options['ck'])) {
				$q['ck'] = $this->options['ck'];
			}
			if (isset($this->options['db'])) {
				$q['db'] = $this->options['db'];
			}

			if (self::$in_transaction) {
				$q['transaction'] = true;
			}

			$rv = fi::query($q);
			fi::$warn_on_duplicate_key = $old_warn_flag;

			if ($w = fi::warnings()) {
				debug('mod::exec warnings', $w, $q, $args);
			}
			if ($rv === false) {
				if (fi::error(0) == fi::errno_duplicate) {
					if ($this->warn_on_duplicate_key) {
						debug('mod::exec warning '.fi::error(1), $q, $args);
					}
					return false;
				} else {
					trigger_error('mod::exec() failed executing '.fi::error(0).' ('.fi::error(1).') '.($q && is_array($q) ? $q['query'] : $q).' '.print_r($args, 1), E_USER_ERROR);
				}
			}
		}
		mod_wtables::add($this->table);
		return $rv === 0
			? '0E0'
			: $rv;
	}

	public function destroy() {
		$this->has_run = true;
		$this->table = null;
	}

	public function __destruct() {
		if (!$this->has_run) {
			error('mod:: query prepared but never exec()\'d: '.$this->mode.' '.$this->table);
		}
	}

	public static function start_transaction() {
		if (!config::$do_transactions) {
			return false;
		}
		if (self::$should_start_transaction) {
			return false;
		} else {
			self::$should_start_transaction = true;
			return true;
		}
	}

	private function _real_start_transaction() {
		if (self::$should_start_transaction && !self::$in_transaction) {
//			fi::start_transaction();
			self::$in_transaction = true;
			
			if (!self::$exit_handler_registered) {
				trigger::register('exit', array('mod','exit_transaction_check_cb'));
				self::$exit_handler_registered = true;
			}
		}
	}

	public static function commit_transaction() {
		if (self::$in_transaction) {
			fi::commit_transaction();
			self::$in_transaction = false;
		}
		self::$should_start_transaction = false;
	}

	public static function in_transaction() {
		return self::$should_start_transaction;
	}

	public static function exit_transaction_check_cb() {
		if (@$GLOBALS['tr_count']) {
			debug('transaction count: '.$GLOBALS['tr_count']);
		}
		if (self::$in_transaction) {
			debug('ERROR: MOD has unfinished transaction!');
		}
	}
}

class mod_wtables {
	static $wtables = array();
	public static function add($table) {
		self::$wtables[ $table ] = 1;
	}
	public static function get() {
		return self::$wtables;
	}
	public static function affected($table) {
		return isset(self::$wtables[$table]);
	}
}
