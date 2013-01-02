<?php

class sel {
	static $global_debug = false;

	public $debug_mode = false;

	public $opts = array();

	private $table;
	private $joins;
	private $joins_alias;
	private $select;
	private $where;
	private $group_by;
	private $order_by;
	private $limit;
	private $having;
	private $index_hints;
	private $sub_sel_query_tables;

	private $count_query;

	private $mysql_options;

	private $where_op = null;

	private $sub_queries;

	private $args;

	private $set_connection_by_tables_has_run = false;

	private $ck;

	const NO_ID_TABLE = 2;
	const NORMAL_TABLE = 1;

	protected $has_run = false;

	private function set_connection_by_tables() {
		if ($this->set_connection_by_tables_has_run) {
			return;
		}
		// force select to go to the rw connection if any table we are querying has been affected by a mod:: during this execution
		foreach ($this->used_tables() as $t) {
			if (mod_wtables::affected($t)) {
//				debug('SETTING Q TO RW BECAUSE OF '.$t);
				$this->ck = config::$dbck['rw'];
				break;
			}
		}
		$this->set_connection_by_tables_has_run = true;
	}

	public function opt($opt, $val) {
		$this->opts[$opt] = $val;
		return $this;
	}

	public static function global_debug($set = true) {
		self::$global_debug = $set;
	}

	public function debug($set = true) {
		$this->debug_mode = $set;
		return $this;
	}

	public static function from($table,$tabletype=null,$options = array(), $sel_options = array()) {
		if ($tabletype === null) {
			$tabletype = self::NORMAL_TABLE;
		}
		$sel = new self($table, $tabletype, $options, $sel_options);
		if (self::$global_debug) {
			$sel->debug(1);
		}
		return $sel;
	}

	public static function union($queries) {
		$q = new union_sel($queries);
		return $q;
	}

	public static function union_exec($queries, $ck = null) {
		if ($ck === null) {
			$ck = config::$dbck['ro'];
		}

		$query = array();
		$args = array();
		$debug_mode = false;
		$debugcount = 0;
		foreach ($queries as $q) {
			if ($q->debug_mode) {
				$debug_mode = true;
				$debugcount++;
			}
			if ($debugcount>1) {
				$q->debug_mode = false;
			}
			list($m, $s) = $q->assemble();
			if (count($s)) {
				error('sel::union_exec(): cannot contain sub-queries');
			}
			$q->has_run = true;
			list($mq, $ma) = $m;
			
			$query[] = '('.$mq.')';
			if (is_array($ma)) {
				$args = array_merge($args, $ma);
			}
		}
		$query = implode(' UNION ', $query);
		if ($debug_mode) {
			debug('union_exec', $query, $args);
			debug_time('union_exec timing');
		}

		$q = array(
			'query' => $query, 
			'args' => $args,
			'ck' => $ck,
		);
		/*if (isset($this->opts['db'])) {
			$q['db'] = $this->opts['db'];
		}*/
		$r = fi::query($q);

		$r = $r->fetch_all();
		if ($debug_mode) {
			debug_time('union_exec timing');
		}
		return $r;
	}

	function __construct($table, $tabletype ,$options = array(), $sel_options = array()) {
		$ck = config::$dbck['ro'];
		if (isset($sel_options['ck'])) {
			$ck = $sel_options['ck'];
			unset($sel_options['ck']);
		}

		$this->opts = $sel_options + array(
			'cache_counts' => true,
		);

		$this->table = $table;
		$this->tabletype = $tabletype;
		$this->mysql_options = $options;
		$this->index_hints = array();
		$this->having = $this->where = array();
		$this->group_by = $this->order_by = $this->limit = array();
		// keys: table alias(or real tblname if not aliased), values: join crit (using/or)
		$this->joins = array();
		// maps from join alias(or real table name if not aliased) to real table name
		$this->joins_alias = array();
		$this->select = array();
		$this->sub_queries = array();
		$this->args = array(
			'where' => array(),
			'having' => array(),
			'join' => array(),
		);

		$this->ck = $ck;

		$this->where_op = 'AND';

		$this->sub_sel_query_tables = array();

		if ($this->tabletype == self::NORMAL_TABLE) {
			$this->select[] = $this->pkname($this->table);
		}
	}

	public function used_tables(&$tables = -1) {
		$t = array($this->table);
		$t = array_merge(
			$t,
			array_values($this->joins_alias),
			array_keys($this->sub_queries),
			$this->sub_sel_query_tables
		);
		if ($tables === -1) {
			return $t;
		} else {
			if (!is_array($tables)) {
				$tables = array();
			}
			$tables = array_merge($tables, $t);
			return $this;
		}
	}

	public function count_query($query) {
		$args = func_get_args();
		array_shift($args);
		call_args_flatten($args);
		$this->count_query = array($query, $args);
		return $this;
	}

	public function index_hint($table, $hint = null) {
		if ($hint === null) {
			unset($this->index_hints[$table]);
		} else {
			$this->index_hints[$table] = $hint;
		}
		return $this;
	}

	public function connection($ck) {
		$this->ck = $ck;
		return $this;
	}

	public function fields($fields = null) {
		if ($fields === null) {
			foreach ($this->joins as $k => $j) {
				// only unset if set with fields()
				if (!is_array($j)) {
					unset($this->joins[$k]);
				}
			}
			$this->select = array();
			$this->sub_queries = array();
			if ($this->tabletype == self::NORMAL_TABLE) {
				$this->select[] = $this->pkname($this->table);
			}
		} else {
			$fields = func_get_args();
			call_args_flatten($fields);

			$select = array();
			foreach ($fields as $f) {
				if ($f[0] == '!') {
					$raw = substr($f, 1);
					$select[] = $raw;
				} else {
					if (strpos($f, ' ') !== false) {
						list($fieldspec, $alias) = explode(' ', $f, 2);
					} else {
						$fieldspec = $f;
						$alias = null;
					}
					$parts = explode('/', $fieldspec);
					$field_name = array_shift($parts);
					/* !...raw
					 * fieldname/remote_table: left join remote_table using(remote_table . _id)
					 * *fieldname/remote_table: select our_table . _id, fieldname from remote_table where our_table . _id = $id_of_parent_rec
					 * #fieldname/remote_table: select our_table . _id, fieldname from remote_table left join $kapcs_table using(remote_table . _id) where our_table . _id = $id_of_parent_rec
					 */
					if ($f[0] == '*') {
						if (empty($parts)) {
							trigger_error('* '.$fieldspec.' subquery needs a remote table', E_USER_WARNING);
							continue;
						}
						$field_name = substr($field_name, 1);
						if (!isset($this->sub_queries[$parts[0]])) {
							$this->sub_queries[$parts[0]] = array();
							$this->sub_queries[$parts[0]]['fields'] = array("`{$parts[0]}`.`{$this->table}_id`");
						}
						if (!$alias) {
							$alias = $parts[0].'_'.$field_name;
						}
						$this->sub_queries[$parts[0]]['fields'][] = "`{$parts[0]}`.`$field_name` AS `$alias`";
					} else if ($f[0] == '#') {
						$field_name = substr($field_name, 1);
						if (!isset($this->sub_queries[$parts[0]])) {
							$this->sub_queries[$parts[0]] = array();

							$link_table_name = $this->mklinktbl($parts[0], $this->table);
							$this->sub_queries[$parts[0]]['fields'] = array("`$link_table_name`.`{$this->table}_id`", "`{$parts[0]}`.`{$parts[0]}_id`");

							$this->sub_queries[$parts[0]]['join'] = "LEFT JOIN `$link_table_name` USING(`{$parts[0]}_id`)";
							$this->sub_queries[$parts[0]]['link_table'] = $link_table_name;
						}
						if (!$alias) {
							$alias = $parts[0].'_'.$field_name;
						}
						$this->sub_queries[$parts[0]]['fields'][] = "`{$parts[0]}`.`$field_name` AS `$alias`";
					} else {
						if (empty($parts)) {
							$selfld = "`{$this->table}`.`$field_name`";
							if ($alias) {
								$selfld .= " AS `$alias`";
							}
							$select[] = $selfld;
						} else if (count($parts) == 1) {
							if (!$alias) {
								$alias = $parts[0].'_'.$field_name;
							}
							$select[] = "`{$parts[0]}`.`$field_name` AS `$alias`";
							if (isset($this->joins[$parts[0]]) and is_array($this->joins[$parts[0]])) {
								error("this table {$parts[0]} already joined with join()");
							}
							// fix here if fields() and join() overwrite each other and it's a problem
							$this->joins[$parts[0]] = $parts[0].'_id';
						}
					}
				}
			}
			$this->select = array_merge($this->select, $select);
		}

		return $this;
	}

	/* fix the arguments, so joins can overwrite each other 
	 * if $on === null, remove a table from the join,
	 * if !== null, but is already set, AND it together
	 * watch for the args */
	public function join($table = null, $on = null) {
		if ($table === null) {
			foreach ($this->joins as $k => $j) {
				// only unset if set with join()
				if (is_array($j)) {
					unset($this->joins[$k]);
					unset($this->joins_alias[$k]);
				}
			}
		} else {
			$alias = $table;
			if (strpos($table, ' ') !== false) {
				list($table, $alias) = explode(' ', $table, 2);
			}

			if ($on === null) {
				unset($this->joins[$alias]);
				unset($this->args['join'][$alias]);
				unset($this->joins_alias[$alias]);
			} else {
				$args = func_get_args();
				$args = array_splice($args, 2);
				if (!isset($this->joins[$alias])) {
					$this->joins[$alias] = array();
				} else if (!is_array($this->joins[$alias])) {
					error("this table $table -> $alias already joined with fields()");
				}
				$this->joins_alias[$alias] = $table;
				$this->joins[$alias][] = $on;
				if (!isset($this->args['join'][$alias])) {
					$this->args['join'][$alias] = array();
				}
				if (!empty($args)) {
					$this->args['join'][$alias] = array_merge($this->args['join'][$alias], $args);
				}
			}
		}
		return $this;
	}

	public function where_AND() {
		$this->where_op = 'AND';
		return $this;
	}

	public function where_OR() {
		$this->where_op = 'OR';
		return $this;
	}

	// if === null, reset the where, and args
	// else just add, already okay
	public function where($where = null) {
		if ($where === null) {
			$this->where = array();
			$this->args['where'] = array();
		} else {
			$where = func_get_args();
			call_args_flatten($where);
			$str = array_shift($where);
			if ($str[0] == '#') {
				$str = substr($str, 1);
				list($tbl, $rest) = explode(' ', $str, 2);
				$this->sub_queries[$tbl]['where'][] = $rest;
				if (!empty($where)) {
					if (!@is_array($this->sub_queries[$tbl]['where_args'])) {
						$this->sub_queries[$tbl]['where_args'] = array();
					}
					$this->sub_queries[$tbl]['where_args'] = array_merge($this->sub_queries[$tbl]['where_args'], $where);
				}
			} else {
				if (!empty($where)) {
					$processed_where = array();
					foreach ($where as $i => &$w) {
						if ($w instanceof sel) {
							$this->sub_sel_query_tables = array_merge($this->sub_sel_query_tables, $w->used_tables());
							list($main_q, $sub_qs) = $w->assemble();
							$w->has_run = true;

							if (!empty($sub_qs)) {
								error('sel::where(): cannot insert sel:: query with subqueries');
							}

							list($main_q, $args) = $main_q;
							foreach ($args as $a) {
								$processed_where[] = $a;
							}
							$str = replace_ith('?', $i, $str, $main_q);
						} else {
							$processed_where[] = $w;
						}
					}
					unset($w);
					$this->args['where'] = array_merge($this->args['where'], $processed_where);
				}
				$this->where[] = $str;
			}
		}
		return $this;
	}

	public function where_keys($snippet, $ids, $string_quote = false) {
		if (empty($ids)) {
			error('sel::where_keys called with no ids');
		}
		if ($string_quote) {
			foreach ($ids as &$i) {
				$i = "'".fi::quote($i)."'";
			}
			unset($i);
		} else {
			foreach ($ids as &$i) {
				$i = @intval($i);
			}
			unset($i);
		}
		$snippet = str_replace('(?)', '('.implode(', ', $ids).')', $snippet);
		$this->where[] = $snippet;
		return $this;
	}

	// same as where
	public function having($having = null) {
		if ($having === null) {
			$this->having = array();
		} else {
			$having = func_get_args();
			call_args_flatten($having);
			$str = array_shift($having);
			if (!empty($having)) {
				$this->args['having'] = array_merge($this->args['having'], $having);
			}
			$this->having[] = $str;
		}
		return $this;
	}


	public function limit($limit = null, $n = null) {
		if ($limit === null) {
			$this->limit = array();
		} else {
			if (!is_numeric($limit)) {
				error('sel: limit/1 is '.$limit.', expected a number');
			}
			$this->limit = array($limit);
			if ($n !== null) {
				if (!is_numeric($n)) {
					error('sel: limit/2 is '.$n.', expected a number');
				}
				$this->limit[] = $n;
			}
		}
		return $this;
	}

	/* make order by callable multiple times, reset as usual */
	public function order_by($order_by = null) {
		if ($order_by === null) {
			$this->order_by = array();
		} else {
			$order_by = func_get_args();
			call_args_flatten($order_by);
			$mq_ob = $sq_ob = array();
			foreach ($order_by as $o) {
				if (strpos($o, ' ') === false) {
					trigger_error("error, order_by $o has no order in it", E_USER_ERROR);
				}

				list($fldspec, $order) = explode(' ', $o, 2);

				$order = strtoupper($order);

				if ($order != 'DESC' and $order != 'ASC') {
					error('sel::order_by expected ASC or DESC as order');
				}

				if ($fldspec[0] == '#') {
					if (strpos($fldspec, '/') === false) {
						trigger_error("error, order_by $fldspec has no / in it", E_USER_ERROR);
					}
					$fldspec = substr($fldspec, 1);
					list($fld, $tbl) = explode('/', $fldspec);
					$fld = str_replace('.', '`.`', $fld);
					$sq_ob[$tbl][] = "`$fld` $order";

				} else if ($fldspec[0] == '!') {
					$fldspec = substr($fldspec, 1);
					$mq_ob[] = "$fldspec $order";

				} else {
					$mq_ob[] = "`$fldspec` $order";
				}
			}
			if (!empty($mq_ob)) {
				$this->order_by = array_merge($this->order_by, $mq_ob);
			}
			foreach ($sq_ob as $table => $obs) {
				$this->sub_queries[$table]['order_by'] = 'ORDER BY '.implode(', ', $obs);
			}
		}
		return $this;
	}

	// same old, same old
	public function group_by($group_by = null) {
		if ($group_by === null) {
			$this->group_by = array();
		} else {
			$group_by = func_get_args();
			call_args_flatten($group_by);
			$this->group_by = array_merge($this->group_by, $group_by);
		}
		return $this;
	}

	private function pkname($table) {
		return '`'.$table.'`.`'.$table.'_id`';
	}

	private function mklinktbl($a, $b) {
		if (strcmp($a, $b) < 0) {
			return $a.'_'.$b;
		}
		return $b.'_'.$a;
	}

	private function do_query($query, $args = array(), $debug_prefix = '') {
		$this->set_connection_by_tables();
		if ($this->debug_mode) {
			debug(trim('do_query '.$debug_prefix), $query, $args);
			debug_time('do_query timing');
		}
		$q = array(
			'query' => $query, 
			'args' => $args,
			'ck' => $this->ck,
		);
		if (isset($this->opts['db'])) {
			$q['db'] = $this->opts['db'];
		}
		$r = fi::query($q);
		if ($r) {
			if (is_object($r) && $r instanceof fis) {
				$result = $r->fetch_all();
				if ($this->debug_mode) {
					debug_time('do_query timing');
				}

				return $result;
			}
			else {
				trigger_error('fi::query returned something unexpected from '.$query, E_USER_WARNING);
				return null;
			}
		} 
		else {
			trigger_error('error executing query: '.$query.'; reason: '.fi::error(true), E_USER_WARNING);
			return null;
		}
	}

	public function count() {
		$count_cache_key = null;
		if (!empty($this->count_query)) {
			list($q, $args) = $this->count_query;
			$count_cache_key = md5($q.json_encode($args));
			if (cache_fetch('cnt-'.$count_cache_key, $r)) {
				return $r;
			}
			$res = $this->do_query($q, $args, 'count_explicit');
			$count = current($res[0]);
		} else {
			list($main_q) = $this->assemble(true); // count_query
			list($main_q, $args) = $main_q;
			$count_cache_key = md5($main_q.json_encode($args));
			if (cache_fetch('cnt-'.$count_cache_key, $r)) {
				return $r;
			}
			$res = $this->do_query($main_q, $args, 'count');
			$count = $res[0]['c'];
		}
		$this->has_run = true;
		if ($this->opts['cache_counts'] and $count_cache_key) {
			model_runcache::add_count('cnt-'.$count_cache_key, $count);
		}
		return $count;
	}

	public function exec($field = null) {
		$rv = $this->real_exec();
		if ($field !== null) {
			foreach ($rv as &$r) {
				$r = $r[$field];
			}
			unset($r);
		}
		return $rv;
	}

	public function exec_one($force = false) {
		$rv = $this->real_exec();
		if (empty($rv)) {
			return $rv;
		}
		if (!$force and count($rv) > 1) {
			error('sel::exec_one() called on a query returning multiple rows');
		}
		return $rv[0];
	}

	public function slice($id, $count, $before = true, $idname = null) {
		$cnt = $count+1;
		$has_more = false;
		if (!$idname) {
			if ($this->tabletype == self::NORMAL_TABLE) {
				$idname = $this->pkname($this->table);
			} else {
				error("sel::slice called on NO_ID_TABLE without an idname!");
			}
		}
		if ($before) {
			$order_dir = "DESC";
			if ($id) {
				$this->where($idname." < ?", $id);
			}
		} else {
			$order_dir = "ASC";
			$this->where($idname." > ?", $id);
		}
		if ($dotpos = strpos($idname, ".")) {
			$id_short_name = substr($idname, $dotpos+1);
		} else {
			$id_short_name = $idname;
		}
		$id_short_name = str_replace('`', '', $id_short_name);
		$ordering = $id_short_name." ".$order_dir;
		$this->order_by($ordering);
		$ret = $this->limit($cnt)->exec();
		if (is_array($ret) && count($ret) == $cnt) {
			$has_more = true;
			$ret = array_slice($ret, 0, -1);
		}
		return array($ret, $has_more);
	}
	
	public function pager($pgvar, $pgsize, &$controls) {
		if (!empty($this->limit)) {
			error("sel::pager cannot be LIMIT-ed by user");
		}

		$controls = array(
			'cnt' => 0,
			'pgsize' => $pgsize,
			'pgcnt' => 0,
			'pos' => 0,
			'has_prev' => 0,
			'rec_from' => 0,
			'rec_to' => 0,
			'prev' => array(),
			'has_next' => 0,
			'next' => array(),
			'first' => array(),
			'last' => array(),
			'pages' => array(),
		);

		$cnt = $this->count();
		$controls['cnt'] = $cnt;

		$pages = floor($cnt / $pgsize);
		if ($cnt % $pgsize) {
			$pages += 1;
		}

		$controls['pgcnt'] = $pages;

		$pos = intval($pgvar);
		list($pos) = input::check_var($pos, input::mangle('int_between', 0, max($pages-1, 0)));
		list($prev) = input::check_var($pos-1, input::mangle('int_between', 0, max($pages-1, 0)));
		list($next) = input::check_var($pos+1, input::mangle('int_between', 0, max($pages-1, 0)));

		$controls['pos'] = $pos;
		$controls['rec_from'] = $pos*$pgsize+1;
		$controls['rec_to'] = ($pos+1)*$pgsize;
		if ($controls['rec_to'] > $cnt) {
			$controls['rec_to'] = $cnt;
		}

		if ($prev != $pos) {
			$controls['has_prev'] = 1;
			$controls['prev'] = array(
				'pos' => $prev,
				'label' => $prev+1,
			);
		}
		if ($next != $pos) {
			$controls['has_next'] = 1;
			$controls['next'] = array(
				'pos' => $next,
				'label' => $next+1,
			);
		}
		
		$pgs = array(1=>true,2=>true,3=>true,4=>true,5=>true);
		for ($i = $pages-5; $i < $pages; $i++) {
			$pgs[$i] = true;
		}
		for ($i = $pos - 5; $i < $pos +6; $i++) {
			$pgs[$i] = true;
		}
			
		foreach (array_keys($pgs) as $i) {
			if ($i >= 0 && $i < $pages) {
				$controls['pages'][] = array(
					'pos' => $i,
					'label' => $i+1,
					'curr' => ($i == $pos),
				);
			}
		}
		
		$controls['first'] = array(
			'pos' => 0,
			'label' => 1,
			'curr' => ($pos == 0),
		);
		
		$controls['last'] = array(
			'pos' => max($pages-1, 0),
			'label' => $pages,
			'curr' => ($pos == max($pages-1,0)),
		);

		$this->limit($pos*$pgsize, $pgsize);
		return $this->exec();
	}

	private function real_exec() {
		list($main_q, $sub_qs) = $this->assemble();
		list($main_q, $args) = $main_q;

		$pks = array();
		$main_r_idx = array();
		$main_r = $this->do_query($main_q, $args);

		if (count($sub_qs) and $this->tabletype != self::NO_ID_TABLE) {
			if ($main_r) {
				foreach ($main_r as $mridx => $mr) {
					$pks[] = $mr[$this->table.'_id'];
					if (!isset($main_r_idx[$mr[$this->table.'_id']])) {
						$main_r_idx[$mr[$this->table.'_id']] = array();
					}
					$main_r_idx[$mr[$this->table.'_id']][] = $mridx;

				}
			}
			if (!empty($pks)) {
				foreach ($sub_qs as $remote_tbl => $q) {
					list($q, $args) = $q;
					$q = sprintf($q, implode(', ', $pks));
					$r = $this->do_query($q, $args, 'sub');
					foreach ($r as $subrec) {
						$idxs = $main_r_idx[ $subrec[$this->table .'_id'] ];
						unset($subrec[$this->table.'_id']);

						foreach ($idxs as $idx) {
							if (!@is_array($main_r[$idx][$remote_tbl])) {
								$main_r[$idx][$remote_tbl] = array();
							}
	
							$main_r[ $idx ][$remote_tbl][] = $subrec;
						}
					}
				}
			}
		} else if (count($sub_qs)) {
			trigger_error("having subquerys with NO_ID_TABLE", E_USER_ERROR);
		}
		$this->has_run = true;

		return $main_r;
	}

	private function consistency_check() {
		if (!isset($this->table)) {
			error('sel: query called with no table');
		}

		/*if (!empty($this->having) and empty($this->group_by)) {
			error('sel: query has HAVING but no GROUP BY');
		}*/

		if (empty($this->select)) {
			error('sel: no fields() set on query');
		}

		if (empty($this->where)) {
			error('sel: no where() set on query');
		}
	}

	// fix this so it doesnt destroy anything,
	// and can be called multiple times
	public function assemble($count_query = false) {
		// this throws an error on any problem
		$this->consistency_check();
		
		// we simply wrap the query in a count(1) subquery
		$subselect_count_query = ($count_query and !empty($this->group_by));

		$args = array();
		if ($count_query and !$subselect_count_query) {
			$select = 'COUNT(1) AS `c`';
		}
		else {
			$select = implode(', ', $this->select);
		}
		$query = "SELECT ".($this->debug_mode && !$subselect_count_query ? 'SQL_NO_CACHE ' : '').($this->mysql_options ? implode(' ', $this->mysql_options).' ' : '')."{$select} FROM `{$this->table}`";
		if (isset($this->index_hints[$this->table])) {
			$query .= " USE INDEX (".$this->index_hints[$this->table].")";
		}

		foreach ($this->joins as $jtbl => $jons) {
			$query .= ' LEFT JOIN ';
			if ($jt_real_name = @$this->joins_alias[$jtbl]) {
				$query .= '`'.$jt_real_name.'` AS `'.$jtbl.'` ';
			} else {
				$query .= '`'.$jtbl.'` ';
			}
			if (isset($this->index_hints[$jt_real_name])) {
				$query .= "USE INDEX (".$this->index_hints[$jt_real_name].") ";
			}
			if (is_array($jons)) {
				// really simple join condition added via ->join(), use USING
				if (count($jons) == 1 and strcspn($jons[0], ".= ") === strlen($jons[0])) {
					$query .= 'USING (`'.$jons[0].'`)';
				}
				else {
					$query .= 'ON ('.implode(') AND (', $jons).')';
				}
			} else {
				$query .= 'USING (`'.$jons.'`)';
			}
			if (isset($this->args['join'][$jtbl])) {
				$args = array_merge($args, $this->args['join'][$jtbl]);
			}
		}

		$query .= ' WHERE ('.implode(') '.$this->where_op.' (', $this->where).')';
		$args = array_merge($args, $this->args['where']);

		if (!empty($this->group_by)) {
			$query .= ' GROUP BY '.implode(', ', $this->group_by);
		}

		if (!empty($this->having)) {
			$query .= ' HAVING ('.implode(') AND (', $this->having).')';
			$args = array_merge($args, $this->args['having']);
		}

		if (!$count_query and !empty($this->order_by)) {
			$query .= ' ORDER BY '.implode(', ', $this->order_by);
		}

		if (!empty($this->limit)) {
			$query .= ' LIMIT '.implode(', ', $this->limit);
		}

		$queries = array();

		if (!$count_query) {
			foreach ($this->sub_queries as $remote_table => $sq) {
				$fields = implode(', ', $sq['fields']);
				$join = '';
				$order_by = '';
				$where = '';
				$sqargs = array();
				$table_with_id = $remote_table;
				if (isset($sq['join'])) {
					$join = $sq['join'].' ';
					$table_with_id = $sq['link_table'];
				}
				if (isset($sq['order_by'])) {
					$order_by = ' '.$sq['order_by'];
				}
				if (isset($sq['where'])) {
					$where = ' AND ('.implode(') AND (', $sq['where']).')';
					if (isset($sq['where_args'])) {
						$sqargs = $sq['where_args'];
					}
				}
				$queries[$remote_table] = array(
					"SELECT ".($this->debug_mode ? 'SQL_NO_CACHE ' : '')."$fields FROM `$remote_table` ${join}WHERE `{$table_with_id}`.`{$this->table}_id` IN (%s)$where$order_by",
					$sqargs,
				);
			}
		}

		if ($subselect_count_query) {
			$query = "SELECT ".($this->debug_mode ? 'SQL_NO_CACHE ' : '')."COUNT(1) as c FROM ($query) as `__dummy`";
		}
		
		return array(array($query, $args), $queries);
	}

	public function destroy() {
		$this->has_run = true;
		$this->table = null;
	}
	public function __destruct() {
		if (!$this->has_run) {
			debug('sel:: query prepared but never exec()\'d: '.$this->table);
		}
	}
}

class union_sel extends sel {
	private $queries = array();

	public function __construct($queries) {
		$this->queries = $queries;
	}

	public function assemble($dummy = false) {
		$query = array();
		$args = array();
		$debug_mode = false;
		foreach ($this->queries as $q) {
			if ($q->debug_mode) {
				$debug_mode = true;
			}
			list($m, $s) = $q->assemble();
			if (count($s)) {
				error('sel::union_exec(): cannot contain sub-queries');
			}
			$q->has_run = true;
			list($mq, $ma) = $m;
			
			$query[] = '('.$mq.')';
			if (is_array($ma)) {
				$args = array_merge($args, $ma);
			}
		}
		$query = implode(' UNION ', $query);
		return array(array($query, $args), array());
	}
}

