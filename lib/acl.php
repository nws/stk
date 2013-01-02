<?php

/* stores current role information */
class roles {
	private $role_stack = array();
	private $roles = array();

	function __construct($roles = null) {
		if ($roles !== null) {
			$this->set($roles);
		}
	}

	function set($roles) {
		if (!is_array($roles)) {
			$this->set(array($roles));
		} else {
			$this->roles = $roles;
		}
	}

	function remove($roles) {
		if (!is_array($roles)) {
			$this->remove(array($roles));
		} else {
			$new_roles = array();
			foreach ($this->roles as $r) {
				if (!in_array($r, $roles)) {
					$new_roles[] = $r;
				}
			}
			$this->roles = $new_roles;
		}
	}

	function add($roles) {
		if (!is_array($roles)) {
			$this->add(array($roles));
		} else {
			$this->roles = array_merge($this->roles, $roles);
		}
	}

	function add_before($roles) {
		if (!is_array($roles)) {
			$this->add_before(array($roles));
		} else {
			$this->roles = array_merge($roles, $this->roles);
		}
	}

	function get() {
		return $this->roles;
	}

	function has($roles) {
		foreach ((array)$roles as $r) {
			if (in_array($r, $this->roles)) {
				return true;
			}
		}
		return false;
	}

	function clear() {
		$this->roles = array();
	}

	function push() {
		$this->role_stack[] = $this->roles;
		$this->clear();
	}

	function pop() {
		$this->roles = array_pop($this->role_stack);
	}
}

class acl {
	static $acl = array();
	static $return_default = false;

	static function setup($return_default = false) {
		self::$return_default = $return_default;
	}
	static function check($target, $operation = null, $roles = null) {
		if ($roles === null) {
			$roles = stash()->roles;
		}
		$roles = $roles->get();

		foreach ($roles as $role) {
			if (!isset(self::$acl[$role])) {
				trigger_error("unknown role $role passed to acl::check()", E_USER_ERROR);
			}

			$rv = self::_check(self::$acl[$role], $target, $operation);

			if ($rv !== null) {
				if (!$rv) {
					debug('acl::check FAILED', $target, $operation);
				}
				return $rv;
			}
		}

		return self::$return_default;
	}

	static function _check($acl, $target, $operation) {
		// current role has rule for $target
		if (isset($acl['rules'][$target])) {
			if ($acl !== null and isset($acl['rules'][$target]['operations'][$operation])) {
				return $acl['rules'][$target]['operations'][$operation];
			} else {
				// default rule
				return $acl['rules'][$target]['default'];
			}
		}

		// if we get here, we need to check upwards
		if ($acl['parent'] !== null) {
			return self::_check(self::$acl[ $acl['parent'] ], $target, $operation);
		} else {
			// we are a root-role and havert found anything
			return null;
		}
	}

	static function _create_rule($signum, $target, $operation = null) {
		if ($operation !== null and is_array($operation)) {
			$ret = array();
			foreach ($operation as $o) {
				$ret[] = array($signum, $target, $o);
			}
			return $ret;
		} else {
			return array($signum, $target, $operation);
		}
	}

	static function allow($target, $operation = null) {
		return self::_create_rule(true, $target, $operation);
	}

	static function deny($target, $operation = null) {
		return self::_create_rule(false, $target, $operation);
	}

	static function _flatten_rules($rules) {
		$flattened_rules = array();
		foreach ($rules as $r) {
			if ($r[0] === true or $r[0] === false) {
				$flattened_rules[] = $r;
			} else {
				foreach ($r as $sr) {
					$flattened_rules[] = $sr;
				}
			}
		}
		return $flattened_rules;
	}

	static function define($role_name, $role_parent, $rules) {
		if (isset(self::$acl[$role_name])) {
			trigger_error("cannot redeclare role $role_name", E_USER_ERROR);
		}

		$processed_rules = array();
		$flattened_rules = self::_flatten_rules($rules);

		foreach ($flattened_rules as $r) {
			list($signum, $target, $operation) = $r;
			if (!isset($processed_rules[$target])) {
				$processed_rules[$target] = array(
					'default' => null,
					'operations' => array(),
				);
			}
			if ($operation === null) {
				$processed_rules[$target]['default'] = $signum;
			} else {
				$processed_rules[$target]['operations'][$operation] = $signum;
			}
		}

		$record = array(
			'parent' => $role_parent,
			'rules' => $processed_rules,
		);

		self::$acl[$role_name] = $record;
	}

	static function extend($role_name, $rules) {
		if (!isset(self::$acl[$role_name])) {
			error('acl::extend: cannot extend undefined role name: '.$role_name);
		}

		$rules = self::_flatten_rules($rules);
		foreach ($rules as $r) {
			list($signum, $target, $operation) = $r;
			if ($operation === null) {
				error("acl::extend: cannot set default value with extend for $role_name $target");
			} else {
				self::$acl[$role_name]['rules'][$target]['operations'][$operation] = $signum;
			}
		}
	}

	static function dump() {
		debug('acl rules', self::$acl);
	}
}

