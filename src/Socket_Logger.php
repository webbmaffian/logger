<?php
namespace Webbmaffian\Logger;

abstract class Socket_Logger extends File_Logger {
	protected $socket = null;

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

		$this->socket_write($json, strlen($json));
	}

	abstract protected function socket_write(string $json, int $length): void;
}