<?php
//!allow print_r

function tempfile($options = array()) {
	$options += array(
		'dir' => sys_get_temp_dir(),
		'unlink' => true,
		'prefix' => 'FileTemp-',
		'suffix' => '',
		'rand_len' => 10,
		'open_mode' => 'x+',
	);
	$options['dir'] = rtrim($options['dir'], '/').'/';

	return new FileTemp($options);
}

class FileTemp {
	private static $excl_open_mode = 'x+';
	private static $max_tries = 1000;

	private $file_name, $file_handle, $options;

	public function __construct($options) {
		$this->options = $options;

		$tries = 0;
		while ($tries++ < self::$max_tries) {
			$fn = $this->mkfn();
			$fh = @fopen($fn, self::$excl_open_mode);
			if ($fh) {
				$this->file_name = $fn;
				if ($this->options['open_mode'] !== self::$excl_open_mode) {
					fclose($fh);
					$fh = @fopen($fn, $this->options['open_mode']);
				}
				$this->file_handle = $fh;
				break;
			}
		}

		if (!$this->file_name) {
			throw new Exception("tempfile() failed with options: ".print_r($this->options, true));
		}
	}

	private function mkfn() {
		$middle = substr(md5(rand()), 0, $this->options['rand_len']);
		$fn = $this->options['dir'] . $this->options['prefix'] . $middle . $this->options['suffix'];
		return $fn;
	}

	public function __toString() {
		return $this->name();
	}

	public function name() {
		return $this->file_name;
	}

	public function handle() {
		return $this->file_handle;
	}

	public function __destruct() {
		if ($this->options['unlink']) {
			unlink($this->file_name);
		}
	}
}

