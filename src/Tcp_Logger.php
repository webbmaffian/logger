<?php
namespace Webbmaffian\Logger;
use Exception;

class Tcp_Logger extends Socket_Logger {
	public function __construct(array $options) {
		parent::__construct($options);

		if(function_exists('socket_create')) {
			try {
				if(false === $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
					throw new Exception('Failed to create socket');
				}

				if(!socket_connect($this->socket, $this->options['host'], $this->options['port'])) {
					throw new Exception('Failed to connect to socket');
				}
			}
			catch(Exception $e) {
				$this->socket = null;
			}
		}
	}

	protected function socket_write(string $json, int $length): void {
		socket_write($this->socket, $json, $length);
	}
}