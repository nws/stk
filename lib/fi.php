<?php
/*
 * THE FUCK MYSQLI INTERFACE
 * 2.0:
 * fi became strictly static.
 * fis is strictly non static
 */

define('FI_VERSION', 2.0);

class fi {
	public static $query_count = 0;
	public static $query_count_by_ck = array();
	public static $query_pertile = array();

	public static $warn_on_duplicate_key = true;

	private static $connect_info = array();
	const errno_duplicate = 1062;

	private static $mysqli;
	public static $in_transaction = false;

	public static $ck = null; // current connection key

	static $fis_error = array(0, '');


	public static function disconnect() {
		self::$mysqli = array(); // dc all
	}

	static function _set_error($errno = null, $errstr = null) {
		if ($errno === null) {
			self::$fis_error = array(0, '');
		} else {
			self::$fis_error = array($errno, $errstr);
		}
	}

	public static function error($string = false) {
		if (!isset(self::$mysqli[self::$ck])) {
			if ($string) {
				return mysqli_connect_error();
			} else {
				return mysqli_connect_errno();
			}
		} else if (self::$mysqli[self::$ck]->errno > 0) {
			if ($string) {
				return self::$mysqli[self::$ck]->error;
			} else {
				return self::$mysqli[self::$ck]->errno;
			}
		} else {
			if ($string) {
				return self::$fis_error[1];
			} else {
				return self::$fis_error[0];
			}
		}
	}

	static function warnings() {
		if (self::$mysqli[self::$ck]->warning_count) {
			if (self::real_query('show warnings')) {
				$w = self::real_fetch_all();
				return $w;
			}
		}
	}

	public static function set_connection($ck = null) {
		if ($ck === null) {
			$ck = config::$dbck['rw'];
		}
		if (isset(self::$connect_info[$ck])) {
			self::$ck = $ck;
			self::ensure_dbconnect();

			// switch selects to the readwrite connection for the remainder of this page load
			// this is to work around bugs where the replication is slightly too slow and 
			// the result of an insert/update/delete doesnt appear on the slaves fast enough
			// for us to select
			if (config::$switch_to_rw_db_after_write 
				&& $ck == config::$dbck['rw'] 
				&& config::$dbck['rw'] != config::$dbck['ro']) 
			{
				config::$dbck['ro'] = config::$dbck['rw'];
			}

			return true;
		}
		else {
			error('fi::set_connection('.$ck.'): no such connection set up');
		}
	}

	public static function multi_connect($conn_data) {
		if (self::$ck === null) {
			self::$ck = config::$dbck['rw'];
		}
		foreach ($conn_data as $ck => $cd) {
			self::$connect_info[$ck] = array(
				'host' => $cd['host'],
				'user' => $cd['user'],
				'pass' => $cd['pass'],
				'db' => $cd['db'],
				'port' => $cd['port'],
			);
		}
	}

	static function connect($host, $user, $pass, $db, $port) {
		if (self::$ck === null) {
			self::$ck = config::$dbck['rw'];
		}
		self::$connect_info[config::$dbck['rw']] = array(
			'host' => $host,
			'user' => $user,
			'pass' => $pass,
			'db' => $db,
			'port' => $port,
		);
		return true;
	}

	static function real_connect($connection_key, $host, $user, $pass, $db, $port) {
		self::$mysqli[$connection_key] = @new mysqli($host, $user, $pass, $db, $port);
		if (mysqli_connect_error()) {
			self::$mysqli[$connection_key] = null;
			return false;
		}
		return true;
	}

	static function ensure_dbconnect() {
		if ((!self::$mysqli or !isset(self::$mysqli[self::$ck])) and !empty(self::$connect_info[self::$ck])) {
			debug('real connect '.self::$ck);
			$rv = self::real_connect(
				self::$ck,
				self::$connect_info[self::$ck]['host'],
				self::$connect_info[self::$ck]['user'],
				self::$connect_info[self::$ck]['pass'],
				self::$connect_info[self::$ck]['db'],
				self::$connect_info[self::$ck]['port']
			);
			if (!$rv) {
				error('cannot connect to db '.self::error(1));
			}
			self::$query_count_by_ck[self::$ck] = 0;
			self::query("SET NAMES utf8");
			if (!empty(config::$timezone)) {
				self::query("SET time_zone = '".config::$timezone."'");
			}
		} else {
			return;
		}
	}

	static function prepare($query) {
		self::ensure_dbconnect();
		if (!self::$mysqli[self::$ck]) {
			trigger_error('fi::prepare(): fi::connect() never called for ck = '.self::$ck, E_USER_ERROR);
		}
		//debug('new fis('.self::$ck.', '.$query.')');
		$s = new fis(self::$mysqli[self::$ck], $query);
		if ($s->error) {
			return false;
		}
		return $s;
	}
	static function quote($s) {
		self::ensure_dbconnect();
		return self::$mysqli[self::$ck]->escape_string($s);
	}

	// use with care... fucks over the entire fi thing
	// is here because not all sql statements work with prepared statements
	static function real_query($query, $ck = null) {
		if ($ck === null) {
			$ck = config::$dbck['rw'];
		}
		self::set_connection($ck);
		self::ensure_dbconnect();
		return self::$mysqli[self::$ck]->real_query($query);
	}

	static function real_fetch() {
		return self::$mysqli[self::$ck]->use_result();
	}

	static function real_fetch_all() {
		$r = self::$mysqli[self::$ck]->store_result();
		if ($r) {
			$result = array();
			while ($row = $r->fetch_assoc()) {
				$result[] = $row;
			}
			$r->free();
			return $result;
		}
	}

	static function start_transaction() { // DO NOT CALL THIS DIRECTLY, use mod::start_transaction()
		if (config::$do_transactions) {
			$oldck = self::$ck;
			self::set_connection();
			self::$mysqli[self::$ck]->autocommit(false);
			self::$in_transaction = true;
			self::set_connection($oldck);
		}
	}

	static function commit_transaction() { // DO NOT CALL THIS DIRECTLY, use mod::commit_transaction()
		$oldck = self::$ck;
		self::set_connection();
		self::$mysqli[self::$ck]->commit();
		self::$mysqli[self::$ck]->autocommit(true);
		self::set_connection($oldck);
		if (isset($GLOBALS['tr_count'])) {
			$GLOBALS['tr_count'] = $GLOBALS['tr_count'] + 1;
		} else {
			$GLOBALS['tr_count'] = 1;
		}
		self::$in_transaction = false;
	}

	static function select_db($db) {
		if (!self::real_query('SELECT DATABASE()')) {
			error('fi::select_db() cannot figure out the current default db');
		}

		list($old_db) = self::real_fetch()->fetch_row();
		self::$mysqli[self::$ck]->select_db($db);
		return $old_db;
	}

	static function query($query, $bind_vals = null) {
		$ck = config::$dbck['rw'];
		$db = null;
		$transaction = false;
		if (is_array($query)) { // named arguments
			if (!isset($query['query'])) {
				error('fi::query, called with named params but no query passed');
			}

			if (isset($query['ck'])) {
				$ck = $query['ck'];
			}

			if (isset($query['db'])) {
				$db = $query['db'];
			}

			$bind_vals = (array_key_exists('args', $query) ? (array)$query['args'] : array());
			$query = $query['query'];

			$transaction = isset($query['transaction']);
		}

		self::set_connection($ck);
		if ($transaction) {
			self::start_transaction();
		}

		if ($db) {
			$db = self::select_db($db);
		}

		$s = self::prepare($query);
		if ($s === false) {
			return false;
		}

		if (!is_array($bind_vals)) {
			$bind_vals = func_get_args();
			array_shift($bind_vals);
		}
		$ret = call_user_func_array(array($s, 'execute'), $bind_vals);

		if ($db) {
			self::select_db($db);
		}

		if ($ret === false) { // error
			if (self::$warn_on_duplicate_key or self::error(0) != self::errno_duplicate) {
				debug('bad fi::query', self::error(0), self::error(1));
			}
			return false;
		} else if ($ret === true) { // success, but not an insert, we need fetch
			if (is_object($s) 
				&& $s instanceof fis 
				&& isset($s->affected_rows)
				&& $s->affected_rows >= 0) 
			{
				return $s->affected_rows;
			}
			return $s;
		} else { // success with insert, return insert id
			return $ret;
		}
	}

	// call this to get a transaction object
	// call commit() or rollback() on it when you're done
	// if you dont call either of those, rollback() will be called at destruction
	// these transactions work recursively, so and each commit only commits the innermost
	// so go ahead, be brave.
	static function txn($label = '') {
		$oldck = self::$ck;
		self::set_connection(); // switch to rw
		$txn = new fitxn(self::$mysqli[self::$ck], array('label' => $label));
		self::set_connection($oldck); // switch back
		return $txn;
	}
}

class fis {
	private $mysqli;
	public $error;
	public $insert_id;
	public $affected_rows;
	private $stmt;
	private $result;

	public function __construct($mysqli, $query) {
		$this->mysqli = $mysqli;
		$s = $this->mysqli->prepare($query);
		if (!$s) {
			$this->stmt = null;
			$this->error = $this->mysqli->error;
		} else {
			$this->stmt = $s;
		}
	}

	/* can be called as
	 * val1, val2
	 * array(val1, val2)
	 */
	public function execute() {
		$bind_vals = func_get_args();
		$bind_params = array();
		if (!empty($bind_vals)) {
			if (count($bind_vals) == 1 and is_array($bind_vals[0])) {
				$bind_vals = $bind_vals[0];
			}
			$types = '';
			foreach ($bind_vals as &$b) {
				$bind_params[] = &$b;
				if (is_int($b)) {
					$types .= 'i';
				} else if (is_float($b)) {
					$types .= 'd';
				} else if (is_object($b)) {
					debug('broken bind vals', $bind_vals);
					error('can not bind objects as query parameters, aborting');
				} else {
					$types .= 's';
				}
				unset($b);
			}
			unset($b);
			array_unshift($bind_params, $types);
			if (!call_user_func_array(array($this->stmt, 'bind_param'), $bind_params)) {
				return false;
			}
		}
		$this->insert_id = null;

		fi::$query_count++;
		fi::$query_count_by_ck[fi::$ck]++;

		if (config::$db_debug_mode) {
			$t = t::current_tile();
			if (!isset(fi::$query_pertile[fi::$ck][$t])) {
				fi::$query_pertile[fi::$ck][$t] = 0;
			}
			fi::$query_pertile[fi::$ck][$t]++;
		}

		if (!$this->stmt->execute()) {
			fi::_set_error($this->error(0), $this->error(1));
			return false;
		} else {
			// no error, unset last error (if it was set...)
			fi::_set_error();
		}

		if ($this->stmt->affected_rows != -1 and $this->stmt->insert_id > 0) {
			$this->insert_id = $this->stmt->insert_id;
		}
		$this->affected_rows = $this->stmt->affected_rows;

		$meta = $this->stmt->result_metadata();
		if ($meta) {
			$this->stmt->store_result(); // apparently only selects have metadata... calling store_result after INSERTS made mysqli retain the insert_id indefinitely somehow...
			$fields = $meta->fetch_fields();
			if ($fields) {
				$this->result = array();
				$bind_vals = array();
				foreach ($fields as $f) {
					$this->result[$f->name] = null;
					$bind_vals[] = &$this->result[$f->name];
				}
				call_user_func_array(array($this->stmt, 'bind_result'), $bind_vals);
			}
			$meta->free_result();
		}

		if ($this->insert_id !== null) {
			$this->stmt->free_result();
			return $this->insert_id;
		}
		return true;
	}

	public function error($string = false) {
		if ($string) {
			return $this->stmt->error;
		} else {
			return $this->stmt->errno;
		}
	}

	public function fetch_col() {
		$r = $this->fetch();
		if (!$r) {
			return false;
		}
		list(, $v) = each($r);
		return $v;
	}
	public function fetch_all() {
		$all = array();
		while ($r = $this->fetch()) {
			$all[] = $r;
		}
		$this->free();
		return $all;
	}
	public function fetch() {
		if ($this->stmt->fetch()) {
			$ret = array();
			foreach ($this->result as $k => $v) {
				$ret[$k] = $v;
			}
			return $ret;
		} else {
			return false;
		}
	}
	public function free() {
		$this->stmt->free_result();
	}
}

class fitxn {
	private $connection, $finalized, $label;

	public function __construct($connection, $params) {
		$this->connection = $connection;
		$this->finalized = false;

		$this->label = isset($params['label']) ? ' ('.$params['label'].')': '';

		debug(__CLASS__.$this->label.' start transaction');

		$this->connection->query("START TRANSACTION");
	}

	public function commit() {
		if (!$this->finalized) {
			debug(__CLASS__.$this->label.' commit');
			$this->connection->commit();
			$this->finalized = true;
		}
	}

	public function rollback() {
		if (!$this->finalized) {
			debug(__CLASS__.$this->label.' rollback');
			$this->connection->rollback();
			$this->finalized = true;
		}
	}

	public function __destruct() {
		if (!$this->finalized) {
			debug(__CLASS__.$this->label.' rollback (automatic)');
			$this->connection->rollback();
			$this->finalized = true;
		}
	}
}

