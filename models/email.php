<?php

class email extends model {
	protected function send($from, $to, $email, $args = array()) {
		if (config::$disable_mailer) {
			debug("MAILING DISABLED, NOT SENDING", $from, $to, $email, $args);
			return true;
		}

		list($subj, $html_body, $images) = $this->template($email['subject'], $email['body'], $args, false, true); // !preview, is_html

		inc('pear/Mail');
		inc('pear/Mail/mime');

		$from_with_name = $from;
		if (!empty(config::$mail_backend_params[$from]['name'])) {
			$from_with_name = config::$mail_backend_params[$from]['name']." <$from>";
		}

		$headers = array();
		$headers['From'] = $from_with_name;
		$headers['To'] = $to;
		$headers['Subject'] = $subj;

		$charset = 'utf-8';
		$headers['charset'] = $charset;

		$text_body = html2text($html_body);

		$m = new Mail_mime();
		$m->setTXTBody($text_body);
		$m->setHTMLBody($html_body);

		if (!empty($images)) {
			foreach ($images as $i) {
				$url = $i['url'];
				if ($url[0] == '/') {
					$url = 'http://'.config::$host.'/'.substr($url, 1);
				}
				$file_content = file_get_contents($url);
				$m->addHTMLImage($file_content, 'image/png', $i['name'], false);
			}
		}

		$body = $m->get(array(
			'html_charset' => $charset,
			'head_charset' => $charset,
			'text_charset' => $charset));

		$hdrs = $m->headers($headers);

		if (config::$mail_backend == 'amazon_ses') {
			inc('amazon_ses');
			$mail = new amazon_ses($headers, $html_body, $text_body, config::$aws_key, config::$aws_secret);
		}
		else if (config::$mail_backend == 'mail' 
			|| !isset(config::$mail_backend_params)
			|| !isset(config::$mail_backend_params[$from]['mailer_opts'])) 
		{
			$mail =& Mail::Factory('mail');
		}
		else {
			$mail =& Mail::Factory(config::$mail_backend, config::$mail_backend_params[$from]['mailer_opts']);
		}

		$r = $mail->send($to, $hdrs, $body);

		if (isset($GLOBALS['debugmailer']) && isset($mail->_smtp)) {
			debug('mailer smtp response', $mail->_smtp->getResponse());
		}

		return $r;
	}

	protected function template($subj, $body, $args, $preview = false, $is_html = false) {
		foreach ($args as $k => $v) {
			$body = str_replace('{$'.$k.'}', $v, $body);
			$subj = str_replace('{$'.$k.'}', $v, $subj);
		}
		if ($is_html) {
			return array($subj, $body, array());
		}
		else {
			list($body, $images) = bbcode_parse_extended($body, true, !$preview);
			$body = $this->add_css($body);
		}
		return array($subj, $body, $images);
	}

	protected function get_footer() {
		return '';
	}

	function add_css($t) {
		return $t;
	}
}
