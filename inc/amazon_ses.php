<?php

class amazon_ses {
	public $headers, $html, $text, $ses;

	function __construct($headers, $html, $text, $key, $secret) {
		$this->headers = $headers;
		$this->html = $html;
		$this->text = $text;

		inc('amazon/sdk.class');
		inc('amazon/services/ses.class');
		$this->ses = new AmazonSES($key, $secret);
	}

	function send($i, $dont, $care) {
		$source = $this->headers['From'];

		$destination = array(
			'ToAddresses' => $this->headers['To'],
		);
		if (!empty($this->headers['Bcc'])) {
			$destination['BccAddresses'] = $this->headers['Bcc'];
		}

		$message = array(
			'Subject' => array(
				'Data' => $this->headers['Subject'],
				'Charset' => $this->headers['charset'],
			),
			'Body' => array(
				'Text' => array(
					'Data' => $this->text,
					'Charset' => $this->headers['charset'],
				),
				'Html' => array(
					'Data' => $this->html,
					'Charset' => $this->headers['charset'],
				),
			),
		);

		$rv = $this->ses->send_email($source, $destination, $message);

		debug('AMAZON SES REPLY '.$rv->status, $rv->body);

		return $rv->isOK();
	}
	function get_stats() {
		return array(
			'get_send_quota'=>$this->ses->get_send_quota()->body->GetSendQuotaResult,
			'get_send_statistics'=>$this->ses->get_send_statistics()->body->GetSendStatisticsResult,
		);
	}
}
