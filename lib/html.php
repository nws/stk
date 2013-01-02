<?php

/* html helper class */
class h {
	const br = '<br/>';
	static function _tag($tagname, $attrs, $content = '', $must_close = false) {
		$tag = '<'.$tagname;
		$attributes = array();
		foreach ($attrs as $k => $v) {
			$attributes[] = $k.'="'.$v.'"';
		}
		if (!empty($attributes)) {
			$attributes = implode(' ', $attributes);
			$tag .= ' '.$attributes;
		}
		if ($content) {
			$must_close = true;
		}
		if ($must_close) {
			$tag .= '>'.$content.'</'.$tagname.'>';
		} else {
			$tag .= '/>';
		}
		return $tag;
	}

	static function tag($tagname, $content = '', $attrs = array(), $must_close = false) {
		return self::_tag($tagname, $attrs, $content, $must_close);
	}

	static function div($content = '', $attrs = array()) {
		return self::_tag('div', $attrs, $content, true);
	}

	static function label($label, $content = '') {
		if ($content) {
			$label .= ' '.$content;
		}
		return self::_tag('label', array(), $label);
	}

	static function static_url($item) {
		return config::$webroot.'static/'.$item;
	}

	static function link($type, $href) {
		static $types = array(
			'css' => array('stylesheet', 'text/css'),
		);

		if ($href[0] == '/') {
			$href = config::$webroot.substr($href, 1);
		} else {
			$href = config::$webroot.'static/'.$type.'/'.$href;
		}

		return '<link rel="'.$types[$type][0].'" href="'.$href.'" type="'.$types[$type][1].'" />';
	}

	static function script($src = null, $contents = '') {
		if ($src !== null) {
			if ($src[0] == '/') {
				$src = config::$webroot.substr($src, 1);
			} else {
				$src = ' src="'.config::$webroot.'static/js/'.$src.'"';
			}
		} else {
			$src = '';
		}
		return '<script type="text/javascript"'.$src.'>'.$contents.'</script>';
	}

	static function l($url, $label = null, $attrs = array()) {
		$attrs['href'] = url($url);
		if ($label === null) {
			$label = html_escape($url);
		}
		return self::_tag('a', $attrs, $label, true);
	}
	static function input($name, $value = '', $attrs = array()) {
		$attrs['type'] = 'text';
		$attrs['name'] = $name;
		$attrs['value'] = $value;
		return self::_tag('input', $attrs);
	}
	static function hidden($name, $value, $attrs = array()) {
		$attrs['type'] = 'hidden';
		$attrs['name'] = $name;
		$attrs['value'] = $value;
		return self::_tag('input', $attrs);
	}
	static function file_upload() {

	}
	static function select($name, $options, $selected = null, $attrs = array()) {
		$content = array();
		foreach ($options as $value => $label) {
			$attrs = array();
			if ($selected == $value) {
				$attrs['selected'] = 'selected';
			}
			$content[] = self::_tag('option', $attrs, $label);
		}
		$content = implode('', $content);
		$attrs['name'] = $name;
		return self::_tag('select', $attrs, $content, true);
	}
	static function textarea($name, $value = '', $attrs = array()) {
		static $default_attr = array(
			'rows' => 10,
			'cols' => 30,
		);
		$attrs['name'] = $name;
		$attrs += $default_attr;
		return self::_tag('textarea', $attrs, $value, true);
	}
	static function radio($name, $value, $attrs = array()) {
		$attrs['type'] = 'radio';
		$attrs['name'] = $name;
		$attrs['value'] = $value;
		return self::_tag('input', $attrs);
	}
	static function listing($tree, $attrs = array(), $pass_attrs = array()) {
		$lis = array();

		$sub_attr = array();
		if (!empty($pass_attrs)) {
			$sub_attr = array_shift($pass_attrs);
		}

		$li_attrs = array();
		if (isset($attrs['li_attrs'])) {
			$li_attrs = $attrs['li_attrs'];
		}

		foreach ($tree as $elt) {
			if (is_array($elt)) {
				$elt = self::listing($elt, $sub_attr, $pass_attrs);
			}
			if (isset($li_attrs[0])) {
				$li_attr = array_shift($li_attrs);
			} else {
				$li_attr = $li_attrs;
			}

			$lis[] = self::_tag('li', $li_attr, $elt);
		}
		unset($attrs['li_attrs']);
		return self::_tag('ul', $attrs, implode('', $lis));
	}

	static function table($header, $rows, $attrs = array()) {
		$headers = array();
		$header_order = array();
		foreach ($header as $hkey => $h) {
			$headers[] = self::_tag('th', array(), $h);
			$header_order[] = $hkey;
		}
		$headers = self::_tag('thead', array(), self::_tag('tr', array(), implode('', $headers)));

		$body = array();
		$even = true;
		foreach ($rows as $row) {
			$tr = array();
			foreach ($header_order as $ho) {
				$cell = &$row[$ho];
				$tr[] = self::_tag('td', array(), $cell);
			}
			$body[] = self::_tag('tr', array('class' => $even ? 'even' : 'odd'), implode('', $tr));
			$even = !$even;
		}
		$body = self::_tag('tbody', array(), implode('', $body));

		return self::_tag('table', $attrs, $headers.$body);
	}

	static function submit($label, $name = null, $attrs = array()) {
		$attrs['type'] = 'submit';
		$attrs['value'] = $label;
		if ($name) {
			$attrs['name'] = $name;
		}
		return self::_tag('input', $attrs);
	}
	static function form($action, $method, $content, $attrs = array()) {
		$action = url($action);
		$attrs['action'] = $action;
		$attrs['method'] = $method;
		$content = self::div($content);
		return self::_tag('form', $attrs, $content, true);
	}
	
	static function convert_to_options($dbresultarray, $kfld, $vfld) {
		$list = array();
		foreach ($dbresultarray as $k => $v) {
			$list[$v[$kfld]] = $v[$vfld];
		}
		return $list;
	}
	
	static function convert_arr_to_getparam ($arr,$skip=null) {
		$s = "";
		$first = true;
		if (is_array($skip))
			foreach ($skip as $v)
				unset($arr[$v]);
		
		foreach ($arr as $k => $v) {
			if ($v) {
				$s .= ($first?'':'&').$k.'='.urlencode($v);
				$first = false;
			}
		}
		return $s;
	}
}
