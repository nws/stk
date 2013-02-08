<?php

/*
 * todo:
 * extra_return:
 * 	keep a stack of model calls so extra_return cant overwrite each other
 * 	model_foo->bar
 * 		extra_return(1) # record that we have shit, save previous (if any)
 * 		model_foo->quuz
 * 			extra_return(2)
 * 			<- pop last extra_return, restore from stack
 * 		model_quuz->xyzzy # goes in separate extra return. will not disturb
 * 			extra_return(3)
 *
 * remove listeners debacle... ;)
 *
 * keep central track of used tables
 * each model call must cache on the list of *all* used_tables() calls made during its run (but nothing else)
 * if an is_destructive() was called during the run, abort all caching for anyone
 */

class models {
	private static $loaded = array();
	public static $global_cache_on = true;
	public static $global_cache_get_on = true;

	static function setup() {
	}

	static function get($model_class_spec) {
		if (!isset(self::$loaded[$model_class_spec])) {
			$model_class = str_replace('/', '_', $model_class_spec);
			require_once 'models/'.$model_class_spec.'.php';
			$o = new $model_class;
			self::$loaded[$model_class] = $o;
			foreach ($o->listeners() as $sm) {
				self::get($sm);
			}
		}
		return self::$loaded[$model_class_spec];
	}
}

function m($s) {
	return models::get($s);
}

/* extend this class with your models, then:
 * given a call: $model->foo(), the actual call made will be
 * 	$main_rv = $model->main_foo();
 * 	this is then cached (if desired)
 */
class model {
	const push = 1;
	const set = 2;
	const value = 3;

	private $no_cache = false;

	public $listeners = array();

	static $base_fields = array();

	protected $extra_return = null;
	protected $extra_return_stack = array();


	protected function extra_return($data) {
		$this->extra_return = $data;
	}

	private static function _cache_add_table_key($at,$key) {
		$r = cache_fetch('_cache_affected_table_'.$at,$c);
		if (!$r) {
			$c = array();
		}
		$c[] = $key;
		$r = cache_store('_cache_affected_table_'.$at,$c);
		if (!$r) { // don't leave invalid caches
			cache_clear();
			debug('Failed to store _cache_affected_table_'.$at.' to cache');
		}
	}
	
	private function _cache_invalidate($at) {
		$keys = array();
		$r = cache_fetch('_cache_affected_table_'.$at,$keys);
		if (!$r) {
			return;
		}
		foreach ($keys as $key) {
			cache_delete($key);
			unset(model_runcache::$static_cache[$key]);
		}
		cache_delete('_cache_affected_table_'.$at);
	}

	/* call in non-destructive methods */
	protected function used_tables($affected_tables = array()) {
		model_runcache::add_tables($affected_tables);
	}

	/* call in destructive methods; if this is called, method call will not be cached */
	protected function is_destructive($affected_tables = array()) {
		foreach ($affected_tables as $at) {
			self::_cache_invalidate($at);
		}
		model_runcache::$static_cache = array();
		//debug("__call(clearing cache) for tables: ".implode(', ', $affected_tables));
		$this->no_cache = true;
	}

	private function get_cache_key($method, $args) {
		$key = get_class($this).'/'.$method.'/'.md5(serialize($args));
		if ($extra_key = $this->extra_key($method)) {
			$key .= '/'.$extra_key;
		}
		return $key;
	}

	private function cache_get($key, &$ret) {
		if (isset(model_runcache::$static_cache[$key])) {
			$r = true;
			$ret = model_runcache::$static_cache[$key];
		} else {
			$r = cache_fetch($key, $ret);
		}
		return $r;
	}

	private function cache_put($key, $data, $affected_tables = array()) {
		foreach ($affected_tables as $at) {
			self::_cache_add_table_key($at,$key);
		}
		model_runcache::$static_cache[$key] = $data;
		return cache_add($key, $data);
	}

	/* return extra key params based on method */
	protected function extra_key($method) {
		//debug("__call: base extra_key($method) called");
	}

	// fix this so it caches into ram at least.
	public function __call($mname, $args) {
		$class = get_class($this);

		$cache_key = null;
		if (models::$global_cache_get_on) {
			$cache_key = $this->get_cache_key($mname, $args);

			cache_init();

			$cached_data = null;
			if ($this->cache_get($cache_key, $cached_data) !== false) {
				return $cached_data;
			}
		}

		if (!method_exists($this, $mname)) {
			trigger_error('cannot call '.$class.'->'.$mname.', does not exist', E_USER_ERROR);
		}

		$this->extra_return_stack[] = $this->extra_return;
		$this->extra_return = null;

		model_runcache::new_frame();
		$transaction = mod::start_transaction();
		$main_rv = call_user_func_array(array($this, $mname), $args);

		$return = null;
		if ($this->extra_return !== null) {
			$return = array($main_rv, $this->extra_return);
			$this->extra_return = null;
		} else {
			$return = $main_rv;
		}

		if ($transaction) {
			mod::commit_transaction();
		}

		if (!empty($this->extra_return_stack)) {
			$this->extra_return = array_pop($this->extra_return_stack);
		}

		$affected_tables = model_runcache::get_tables();
		$counts = model_runcache::get_counts();
		model_runcache::end_frame();

		if (models::$global_cache_on && !$this->no_cache && !empty($affected_tables) && $cache_key !== null) {
			$r = $this->cache_put($cache_key, $return, $affected_tables);
			if (!$r) {
				debug('Failed to store '.$cache_key.' in cache');
			}
			foreach ($counts as $key => $cnt) {
				$this->cache_put($key, $cnt, $affected_tables);
			}
		}
		

		return $return;
	}

	public function listeners() {
		return $this->listeners;
	}

	protected function filter_record($fields, $record) {
		$new_record = array();
		foreach ($fields as $key => $f) {
			$source_f = $f;
			if (!is_numeric($key)) {
				$source_f = $key;
			}

			if (array_key_exists($source_f, $record)) {
				$new_record[$f] = $record[$source_f];
			} else if (array_key_exists('!'.$source_f, $record)) {
				$new_record['!'.$f] = $record['!'.$source_f];
			}
		}
		return $new_record;
	}

	protected function clean_record($fields, $record, $die = false) {
		$new_record = array();
		foreach ($fields as $key => $f) {
			$source_f = $f;
			if (!is_numeric($key)) {
				$source_f = $key;
			}

			if (isset($record[$source_f])) {
				$new_record[$f] = $record[$source_f];
			} else if (isset($record['!'.$source_f])) {
				$new_record['!'.$f] = $record['!'.$source_f];
			} else if ($die) {
				debug("field `$f` is not set in record");
				return null;
			}
		}
		return $new_record;
	}

	protected function flip_record(&$rec, $id_key) {
		return flip_record($rec, $id_key);
	}

	public function get_ids(&$rec, $id_key, $permissive = false) {
		return get_ids($rec, $id_key, $permissive);
	}

	protected function merge_records(&$main_rec, &$sub_rec, $main_key, $sub_key, $method = self::push, $value = null) {
		$mapping = array();
		foreach ($main_rec as $i => $r) {
			$mapping[$r[ $main_key ]] = $i;
		}
		switch ($method) {
		case self::push:
			foreach ($sub_rec as $i => $r) {
				if (isset($mapping[$r[$main_key]])) {
					$main_rec[$mapping[$r[ $main_key ]]][ $sub_key ][] = $r;
				}
			}
			break;
		case self::set:
			foreach ($sub_rec as $i => $r) {
				if (isset($mapping[$r[$main_key]])) {
					$main_rec[$mapping[$r[ $main_key ]]][ $sub_key ] = $r;
				}
			}
			break;
		case self::value:
			foreach ($sub_rec as $i => $r) {
				if (isset($mapping[$r[$main_key]])) {
					$main_rec[$mapping[$r[ $main_key ]]][ $sub_key ] = $value;
				}
			}
			break;
		default:
			error('unknown $method = '.$method);
		}
	}

	function main_moved_id($id) {
		$this->used_tables(array('moved'));
		
		//debug("MOVED ID = $id ?");

		$class = get_class($this);
		$r = sel::from('moved',sel::NO_ID_TABLE)
			->fields('newid')
			->where('`type` = ?', $class)
			->where('`id` = ?', $id)
			->exec_one();
		if (!empty($r)) {
			return $r['newid'];
		}
		return null;
	}

	function main_moved_to($id, $newid) {
		$this->is_destructive(array('moved'));

		$class = get_class($this);
		mod::insert('moved')
			->values(array('type' => $class, 'id' => $id, 'newid' => $newid))
			->exec();
	}

	// WHERE will be $field IN ($ids)
	// ie: m('show')->get_moved_ids_mass('id', array_of_show_ids)
	//   returns: array(id => newid, ...)
	// XXX $field = 'newid' is buggy as-is, will not return everything properly
	function main_get_moved_ids_mass($field, $ids, $type = null) {
		$this->used_tables(array('moved'));
		if ($field == 'newid') { // XXX since this direction is broken anyway
			return array();
		}
		if ($type === null) {
			$type = get_class($this);
		}
		$m = sel::from('moved', sel::NO_ID_TABLE)
			->fields('id', 'newid')
			->where('type = ?', $type)
			->where_keys("$field IN (?)", $ids)
			->exec();
		$key_field = ($field == 'id' ? 'newid' : 'id');
		return make_hash($m, $field, $key_field);
	}

	function main_resolve_moved_ids_mass($ids, $type = null) {
		$this->used_tables(array('moved'));

		$ids = array_combine($ids, $ids);
		$original_ids = $ids;

		$nids = 0;

		while ($nids != count($ids) && ($moved = $this->get_moved_ids_mass('id', array_keys($ids), $type))) {
			$nids = count($ids);

			foreach ($moved as $id => $newid) {
				$ids[ $id ] = $newid;
				$ids[ $newid ] = $newid;
			}

		}

		foreach ($original_ids as $id => &$newid) {
			while (isset($ids[ $newid ]) && $newid != $ids[ $newid ]) {
				$newid = $ids[$newid];
			}
		}
		unset($newid);

		return $original_ids;
	}

	function main_clear_cache_on($tables) {
		$this->is_destructive($tables);
	}

	function escape_like($s) {
		return strtr($s, array(
			'_' => '\\_',
			'%' => '\\%',
		));
	}
}

// this class implements the trickle-up stack... whatever that is :)
class model_runcache {
	static $t = array();
	static $top = null;
	static $static_cache = array();

	static function reset() {
		self::$t = array();
		self::$top = null;
		self::$static_cache = array();
	}

	static function add_tables($ts) {
		if (self::$top === null) {
			error('model_runcache::add_tables called before any new_frame()');
		}

		foreach ($ts as $t) {
			self::$t[self::$top]['tables'][$t] = true;
		}
	}

	static function get_tables() {
		if (self::$top === null) {
			error('model_runcache::get_tables called before any new_frame()');
		}
		return array_keys(self::$t[self::$top]['tables']);
	}

	static function add_count($key, $c) {
		if (self::$top === null) { // called before new_frame (or outside models)
			return null;
		}
		self::$t[self::$top]['counts'][$key] = $c;
	}

	static function get_counts() {
		if (self::$top === null or self::$top != 0) { // do not report counts before we're all done
			return array();
		}
		return self::$t[self::$top]['counts'];
	}

	static function new_frame() {
		if (self::$top === null) { // and you can start adding tables
			self::$top = 0;
		} else {
			self::$top++;
		}
		self::$t[self::$top] = array(
			'tables' => array(),
			'counts' => array(),
		);
	}

	static function end_frame() {
		if (self::$top === null) {
			error('model_runcache::end_frame called before any new_frame()');
		} else if (self::$top === 0) {
			self::$top = null;
		} else {
			self::$top--;
			self::$t[self::$top]['tables'] += self::$t[self::$top+1]['tables'];
			self::$t[self::$top]['counts'] += self::$t[self::$top+1]['counts'];
			unset(self::$t[self::$top+1]);
		}
	}
}

