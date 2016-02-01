<?php

class amazon_ses {
	public $headers, $html, $text, $ses;

	function __construct($headers, $html, $text, $key, $secret) {
		$this->headers = $headers;
		$this->html = $html;
		$this->text = $text;

		inc('amazon/aws-autoloader');
		$this->ses = new Aws\Ses\SesClient([
			'version' => 'latest',
			'credentials' => [
				'key' => $key,
				'secret' => $secret,
			],
			'region' => 'us-east-1',
		]);
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

		try {
			$rv = $this->ses->sendEmail([
				'Source' => $source, 
				'Destination' => $destination, 
				'Message' => $message,
			]);

			debug('AMAZON SES REPLY', $rv->toArray());

			return true;
		} catch (Exception $e) {
			debug('AMAZON SES REPLY EXCEPTION', $e->getMessage());
			return false;
		}
	}

	function get_stats() {
		return array(
			'get_send_quota'=>$this->ses->getSendQuota([])->toArray(),
			'get_send_statistics'=>$this->ses->getSendStatistics([])->toArray(),
		);
	}
}
