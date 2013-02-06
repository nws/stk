<?php

class t {
	const extend_push = 0;
	const extend_concat = 1;
	const extend_unshift = 2;
	const extend_merge = 3;

	static $vars = array();
	static $top = null;
	static $files_seen = array();
	static $files_used = array();
	static $continue = true;

	static function setup() {
	}

	static function _used_file($file) {
		self::$files_used[$file] = true;
	}

	static function _has($tile_var, $ofs = 0) {
		if ($ofs < 0 or $ofs > self::$top) {
			trigger_error("t::_has($tile_var, ...) failed, ofs=$ofs is wrong", E_USER_ERROR);
		}
		return isset(self::$vars[self::$top - $ofs]['vars'][$tile_var]);
	}

	/* is $tile_var defined in current scope? */
	static function has($tile_var) {
		return self::_has($tile_var, 0);
	}

	/* is $tile_var defined in parent scope? (mostly useful for subcalls) */
	static function up_has($tile_var, $ofs = 1) {
		return self::_has($tile_var, $ofs);
	}

	/* tries to find a var on stack, if not found, defines in toplevel */
	static function find_has($tile_var) {
		$t = self::$top;
		$ofs = 0;
		while ($t >= 0) {
			if (isset(self::$vars[$t]['vars'][$tile_var])) {
				$ofs = $t;
				break;
			}
			--$t;
		}
		return self::_has($tile_var, self::$top - $ofs);
	}

	static function _get($tile_var, $ofs = 0) {
		if ($ofs < 0 or $ofs > self::$top) {
			trigger_error("t::_get($tile_var, ...) failed, ofs=$ofs is wrong", E_USER_ERROR);
		}
		if (!self::_has($tile_var, $ofs)) {
			trigger_error("t::_get($tile_var, ...) failed, $tile_var is not defined at ofs=$ofs", E_USER_ERROR);
		}
		return self::$vars[self::$top - $ofs]['vars'][$tile_var];
	}

	/* get value of $tile_var from current scope */
	static function get($tile_var) {
		return self::_get($tile_var, 0);
	}

	/* get value of $tile_var from parent scope */
	static function up_get($tile_var, $ofs = 1) {
		return self::_get($tile_var, $ofs);
	}

	static function _def($tile_var, $contents, $ofs = 0) {
		if ($ofs < 0 or $ofs > self::$top) {
			trigger_error("t::_def($tile_var, ...) failed, ofs=$ofs is wrong", E_USER_ERROR);
		}
		self::$vars[self::$top - $ofs]['vars'][$tile_var] = $contents;
	}

	/* set $tile_var to $contents in current scope */
	static function def($tile_var, $contents) {
		self::_def($tile_var, $contents, 0);
	}

	/* set $tile_var to $contents in parent scope */
	static function up_def($tile_var, $contents, $ofs = 1) {
		self::_def($tile_var, $contents, $ofs);
	}

	static function def_hash($hash) {
		foreach ($hash as $k => $v) {
			self::def($k, $v);
		}
	}

	static function _undef($tile_var, $ofs = 0) {
		if ($ofs < 0 or $ofs > self::$top) {
			trigger_error("t::_undef($tile_var, ...) failed, ofs=$ofs is wrong", E_USER_ERROR);
		}
		unset(self::$vars[self::$top - $ofs]['vars'][$tile_var]);
	}

	/* undefine $tile_var in current scope */
	static function undef($tile_var) {
		self::_undef($tile_var, 0);
	}

	/* undefine $tile_var in parent scope */
	static function up_undef($tile_var, $ofs = 1) {
		self::_undef($tile_var, $ofs);
	}

	static function _extend($tile_var, $contents, $extend_method = self::extend_push, $ofs = 0) {
		if ($ofs < 0 or $ofs > self::$top) {
			trigger_error("t::_extend($tile_var, ...) failed, ofs=$ofs is wrong", E_USER_ERROR);
		}
		$pos = self::$top - $ofs;

		if (!isset(self::$vars[$pos]['vars'][$tile_var])) {
			trigger_error("t::_extend($tile_var, ...) failed, $tile_var not defined at ofs=$ofs", E_USER_ERROR);
		}

		switch ($extend_method) {
		case self::extend_push:
			if(!is_array(self::$vars[$pos]['vars'][$tile_var])) {
				self::$vars[$pos]['vars'][$tile_var] = array(self::$vars[$pos]['vars'][$tile_var]);
			}
			self::$vars[$pos]['vars'][$tile_var][] = $contents;
			break;
		case self::extend_unshift:
			if(!is_array(self::$vars[$pos]['vars'][$tile_var])) {
				self::$vars[$pos]['vars'][$tile_var] = array(self::$vars[$pos]['vars'][$tile_var]);
			}
			array_unshift(self::$vars[$pos]['vars'][$tile_var], $contents);
			break;
		case self::extend_merge:
			if(!is_array(self::$vars[$pos]['vars'][$tile_var])) {
				self::$vars[$pos]['vars'][$tile_var] = array(self::$vars[$pos]['vars'][$tile_var]);
			}
			self::$vars[$pos]['vars'][$tile_var] = array_merge(self::$vars[$pos]['vars'][$tile_var], $contents);
			break;
		case self::extend_concat:
			self::$vars[$pos]['vars'][$tile_var] .= $contents;
			break;
		default:
			trigger_error("t::_extend($tile_var, ...) failed, unknown extend_method passed", E_USER_ERROR);
		}
	}

	/* tries to find a var on stack, if not found, defines in toplevel */
	static function find_def($tile_var, $contents) {
		$t = self::$top;
		$ofs = 0;
		while ($t >= 0) {
			if (isset(self::$vars[$t]['vars'][$tile_var])) {
				$ofs = $t;
				break;
			}
			--$t;
		}

		self::_def($tile_var, $contents, self::$top - $ofs);
	}

	/* will seek up the stack for the first defined $tile_var, and extend that */
	static function find_extend($tile_var, $contents, $extend_method = self::extend_push) {
		$t = self::$top;
		$ofs = null;
		while ($t >= 0) {
			if (isset(self::$vars[$t]['vars'][$tile_var])) {
				$ofs = $t;
				break;
			}
			--$t;
		}
		if ($ofs === null) {
			trigger_error("t::find_extend($tile_var, ...) failed, can not find it anywhere on stack", E_USER_ERROR);
		}
		self::_extend($tile_var, $contents, $extend_method, self::$top - $ofs);
	}

	/* extend $tile_var with $contents in current scope (push $contents or concat) */
	static function extend($tile_var, $contents, $extend_method = self::extend_push) {
		self::_extend($tile_var, $contents, $extend_method, 0);
	}

	/* extend $tile_var with $contents in parent scope */
	static function up_extend($tile_var, $contents, $extend_method = self::extend_push, $ofs = 1) {
		self::_extend($tile_var, $contents, $extend_method, $ofs);
	}

	/* set tmpl type and target
	 * types: file, json, smarty, none */
	static function tmpl($type, $tmpl = null,$top=null) {
		if ($top===null) {
			$top = self::$top;
		} else {
			$top = $top;
		}
		self::$vars[$top]['tmpl']['type'] = $type;
		self::$vars[$top]['tmpl']['target'] = $tmpl;

		if (in_array($type, array('json'))) {
			stk_manual_output_filter(true);
		}
	}

	/* makes the current tile process in a new call to t::call(), 
	 * the result is inserted into $tile_var
	 * only makes sense in group tile */
	static function delegate($tile_var) {
		self::$vars[self::$top]['subcall'] = $tile_var;
	}

	/* call from a tile, to cancel a previous t::delegate() call made in a group */
	static function undelegate() {
		if (!isset(self::$vars[self::$top-1])) {
			trigger_error("t::undelegate() called on a too shallow stack", E_USER_ERROR);
		}
		self::$vars[self::$top-1]['self_display'] = true;
	}

	/* once() returns true the first time it's called in a file, false any other time
	 * useful to do one-time init in group tiles that might get included multiple times */
	static function once($block_id = 0) {
		if (isset(self::$files_seen[self::$vars[self::$top]['current_file']][$block_id])) {
			$rv = false;
		} else {
			$rv = true;
		}
		self::$files_seen[self::$vars[self::$top]['current_file']][$block_id] = true;
		return $rv;

	}

	static function current_file() {
		if (isset(self::$top) and isset(self::$vars[self::$top])) {
			return self::$vars[self::$top]['current_file'];
		}
		return null;
	}

	static function current_tile() {
		if (isset(self::$top) and isset(self::$vars[self::$top])) {
			return self::$vars[self::$top]['tile_name'];
		}
		return null;
	}

	/* collect group tiles for given $tile_name */
	static function collect_groups($tile_name) {
		static $cached_groups = array();

		if (!isset($cached_groups[$tile_name])) {

			$loc_path = explode('/', $tile_name);
			array_pop($loc_path);

			$groups = array();
			$groups[] = array('_tiles', true); // is group
			$loc = '';

			while ($p = array_shift($loc_path)) {
				if (is_file('tiles/'.$loc.$p.'/_'.$p.'.php')) {
					$groups[] = array($loc.$p.'/_'.$p, true);
				} else if (is_file('tiles/'.$loc.$p.'/_grouptile.php')) {
					$groups[] = array($loc.$p.'/_grouptile', true);
				}
				$loc .= $p.'/';
			}

			$groups[] = array($tile_name, false);

			$cached_groups[$tile_name] = $groups;
		}

		return $cached_groups[$tile_name];
	}

	static function argc() {
		return count(self::$vars[self::$top]['args']);
	}
	/* without arguments: return all the args passed to the current tile
	 * with an int: return $i'th argument */
	static function argv($i = null, $default = null) {
		if ($i === null) {
			return self::$vars[self::$top]['args'];
		} else {
			if (isset(self::$vars[self::$top]['args'][$i])) {
				return self::$vars[self::$top]['args'][$i];
			} else {
				return $default;
			}
		}
	}
	
	static function set_argv($index, $value) {
		self::$vars[self::$top]['args'][$index] = $value;
	}
	
	static function get_argv_arr($from_index = 0) {
		return array_slice (self::$vars[self::$top]['args'], $from_index);
	}

	static function argv_shift($default = null) {
		if (isset(self::$vars[self::$top]['args'][0])) {
			return array_shift(self::$vars[self::$top]['args']);
		}
		return $default;
	}

	static function require_file($__g) {
		$__fn = 'tiles/'.$__g.'.php';
		self::_used_file($__fn);
		return require $__fn;
	}

	static function is_fcache_on() {
		for ($i = self::$top; $i >= 0; --$i) {
			if (self::$vars[$i]['fcache']) {
				return true;
			}
		}
		return false;
	}

	static function turn_fcache_off() {
		for ($i = self::$top; $i >= 0; --$i) {
			self::$vars[$i]['fcache'] = null;
		}
	}

	static function fcache_display($ttl, $keys = array(), $force_regen = false) {
		if (!config::$fcache_dir) {
			return false;
		}

		self::$vars[self::$top]['fcache'] = array(
			'ttl' => $ttl,
			'keys' => $keys,
		);
		if (($etag = t::fcache_get_etag()) && !$force_regen) {
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']) 
				&& $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				self::send_not_modified();
				stk_exit();
			}
			else if ($c = self::fcache_get()) {
				self::send_etag_header($etag);
				echo $c."<!-- fcache Etag: ".$etag." -->";
				stk_exit();
			}
		}
		return false;
	}

	static function send_not_modified() {
		header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified', 304);
		header('Pragma: public');
		header('Cache-Control: must-revalidate');
	}

	static function send_etag_header($etag) {
		header('Etag: '.$etag);
		header('Pragma: public');
		header('Cache-Control: must-revalidate');
	}
	
	static function fcache_get_etag() {
		$tn = stash()->page;
		$ttl = self::$vars[self::$top]['fcache']['ttl'];
		$keys = &self::$vars[self::$top]['fcache']['keys'];
		$args = &self::$vars[self::$top]['args'];
		$key = md5(serialize(array($tn, $keys, $args)));
		$fn = config::$fcache_dir.$key;
		self::$vars[self::$top]['fcache']['outfile'] = $fn;

		$st = @stat($fn);
		if ($st) {
			if ($st['mtime']+$ttl > stash()->now_ts) {
				return $key.$st['mtime'];
			}
		}
		return false;
	}
	
	static function fcache_get() {
		$tn = stash()->page;
		$ttl = self::$vars[self::$top]['fcache']['ttl'];
		$keys = &self::$vars[self::$top]['fcache']['keys'];
		$args = &self::$vars[self::$top]['args'];

		self::$vars[self::$top]['fcache']['outfile'] 
			= $fn = config::$fcache_dir.md5(serialize(array($tn, $keys, $args)));

		$st = @stat($fn);

		if ($st) {
			if ($st['mtime']+$ttl > stash()->now_ts) {
				return file_get_contents($fn);
			} else {
				@unlink($fn);
			}
		}

		return null;
	}

	static function fcache_put($result) {
		$rv = @file_put_contents(self::$vars[self::$top]['fcache']['outfile'], stk_output_filter($result));
		if ($rv && ($etag = t::fcache_get_etag())) {
			self::send_etag_header($etag);
		}
		return $rv;
	}

	static function end_loop() {
		self::$continue = false;
	}

	private static function _call($tile_name, $args = array(), $is_subcall = 0) {
		// collect groups we havent processed yet
		$group_names = self::collect_groups($tile_name);

		$grp_cnt = count($group_names);

		for ($idx = $is_subcall; $idx < $grp_cnt; ++$idx) {
			// if not the first tile, try for a subcall from the previous one
			if ($idx != $is_subcall and self::$vars[self::$top]['subcall']) {
				self::def(self::$vars[self::$top]['subcall'], self::call('/'.$tile_name, $args, $idx));
				break;
			}

			list($g, $is_group_tile) = $group_names[$idx];

			self::$continue = true;
			$rv = null;

			// XXX how to check if it's not a group
			if ($fn = self::fn_exists($g, $is_group_tile)) {
				self::$vars[self::$top]['current_file'] = $fn.'()';
				//debug("t::call($tile_name): calling $fn");
				$rv = $fn();
			} else {
				self::$vars[self::$top]['current_file'] = $g;
				//debug("t::call($tile_name): requiring $g");
				$rv = self::require_file($g);
			}
			if ($rv === false || !self::$continue) {
				break;
			}
		}

		$subcall_tilevar = self::$vars[self::$top]['subcall'];

		if (self::$vars[self::$top]['self_display']) {
			$result = self::$vars[self::$top]['vars'][$subcall_tilevar];
		} else {
			$result = self::display();
		}

		$result = trim($result);
		
		if (self::$vars[self::$top]['fcache']) {
			self::fcache_put($result);
		}


		return $result;
	}

	static function replace_call($tile_name, $args = array(), $is_subcall = 0) {
		if (!isset(self::$top)) {
			self::$top = 0;
		}

		$tile_name = self::fix_target($tile_name);
		self::init_tvars($tile_name, $args, false);
		self::default_tmpl();

		return self::_call($tile_name, $args, $is_subcall);
	}

	static function merge_call($tile_name, $args = array(), $is_subcall = 0) {
		if (!isset(self::$top)) {
			self::$top = 0;
		}

		$tile_name = self::fix_target($tile_name);
		self::init_tvars($tile_name, $args, false);

		return self::_call($tile_name, $args, $is_subcall);
	}

	/* call $tile_name with $args and return the templated result
	 * if $is_subcall is true, no groups are processed */
	static function call($tile_name, $args = array(), $is_subcall = 0) {
		//debug('t::call('.$tile_name.')');
		if (isset(self::$top)) {
			self::$top++;
		} else {
			self::$top = 0;
		}

		// the default template becomes the fixed tile_name, as an absolute template ref

		$tile_name = self::fix_target($tile_name);
		self::init_tvars($tile_name, $args, true);

		self::tmpl(config::$default_tmpl_type, '/'.$tile_name);

		$result = self::_call($tile_name, $args, $is_subcall);

		if (self::$top > 0) {
			self::$vars[self::$top] = null;
			self::$top--;
		}

		return $result;
	}

	static function default_tmpl() {
		$tile_name = self::$vars[self::$top]['tile_name'];
		self::tmpl(config::$default_tmpl_type, '/'.$tile_name);
	}

	private static function init_tvars($tile_name, $args, $replace = true) {
		if (!$replace && isset(self::$vars[self::$top])) {
			self::$vars[self::$top]['tile_name'] = $tile_name;
			self::$vars[self::$top]['args'] = $args;
			return;
		}
		self::$vars[self::$top] = array(
			'tile_name' => $tile_name,
			'vars' => array(),
			'subcall' => null,
			'self_display' => false,
			'tmpl' => array(
				'type' => null,
				'target' => null,
			),
			'current_file' => null,
			'args' => $args,
			'fcache' => null,
		);
	}

	private static function fn_exists($path, $is_group_tile) {
		$path = strtr($path, '/-', '__');

		if ($is_group_tile) {
			$fn = 'g_'.str_replace('__', '_', $path);
		} else {
			$fn = 't_'.$path;
		}
		if (function_exists($fn)) {
			return $fn;
		}
		return null;
	}

	/* all internal paths can go through this function */
	private static function fix_target($target) {
		if ($target[0] == '/') {
			$target = substr($target, 1);
		}
		return $target;
	}

	/* display tile with the template set earlier */
	static function display() {
		$type = self::$vars[self::$top]['tmpl']['type'];
		$target = self::$vars[self::$top]['tmpl']['target'];
		$vars = self::$vars[self::$top]['vars'];

		switch ($type) {
		case 'text':
			return $target;

		case 'passthrough':
		case 'passthru':
			return @$vars['content'];

		case 'file':
			extract($vars);
			$target = self::fix_target($target);
			ob_start();
			$fn = 'tmpl/'.$target.'.php';
			self::_used_file($fn);
			require $fn;
			return ob_get_clean();
			break;

		case 'func':
			return call_user_func($target, $vars);
			break;

		case 'json':
			header('Content-Type: application/json; charset=utf-8');
			return json_encode($vars);
			break;
		case 'xml':
			header('Content-Type: text/xml; charset=utf-8');
			return '<?xml version="1.0" encoding="UTF-8" ?'.'>'.mb_convert_encoding(array2xml($vars),'utf8','utf8');
			break;

		case 'smarty':
			require_once "smarty/libs/Smarty.class.php";
			$sm = new Smarty;
			$sm->registerFilter('variable', 'escape_html');
			$smarty_config = array(
				'config_dir' => STK_PATH.'smarty/config',
				'compile_dir' => SITE_PATH.'smarty/compile',
				'template_dir' => array(
					SITE_PATH.'tmpl',
					STK_PATH.'tmpl',
				),
				'plugins_dir' => array(
					SITE_PATH.'smarty/plugins',
					STK_PATH.'smarty/plugins',
					SMARTY_DIR.'plugins',
				),
			);

			foreach ($smarty_config as $k => $v) {
				$default_v = isset(config::$smarty[$k]) ? config::$smarty[$k] : null;
				switch ($k) {
				case 'template_dir':
				case 'plugins_dir':
					$v = array_merge((array)$v, (array)$default_v);
					break;
				default:
					$v = $v !== null ? $v : $default_v;
					break;
				}
				$sm->$k = $v;
			}

			$sm->assign('_stash', stash());
			$sm->assign('_config', get_class_vars('config'));
			foreach ($vars as $k => $v) {
				$sm->assign($k, $v);
			}
			$target = self::fix_target($target);
			$fn = $target.'.tpl';
			self::_used_file($fn);
			return $sm->fetch($fn);
			break;

		case 'none':
			return '';
			break;

		default:
			trigger_error("t::display() called with unknown tmpl type: $type", E_USER_ERROR);
			break;
		}
	}
}

