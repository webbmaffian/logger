<?php
namespace Webbmaffian\Logger;

class Udp_Logger extends File_Logger {
	private $socket = null;
	
	public function __construct(array $options) {
		parent::__construct($options);

		$this->socket = (function_exists('socket_create') ? socket_create(AF_INET, SOCK_DGRAM, SOL_UDP) : false);
	}

	public function __destruct() {
		if($this->socket) {
			socket_close($this->socket);
			$this->socket = null;
		}

		parent::__destruct();
	}

	protected function send(string $json): void {

		// If we failed to open socket, fallback to file
		if(!$this->socket) {
			parent::send($json);
			return;
		}

		$length = strlen($json);

		socket_sendto($this->socket, $json, $length, 0, $this->options['host'], $this->options['port']);
	}
}