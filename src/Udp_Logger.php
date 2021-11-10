<?php
namespace Webbmaffian\Logger;

class Udp_Logger extends Socket_Logger {
	public function __construct(array $options) {
		parent::__construct($options);

		if(function_exists('socket_create')) {
			$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		}
	}

	protected function socket_write(string $json, int $length): void {
		socket_sendto($this->socket, $json, $length, 0, $this->options['host'], $this->options['port']);
	}
}