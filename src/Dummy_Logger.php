<?php
namespace Webbmaffian\Logger;

class Dummy_Logger extends Logger {
	protected function send(string $json): void {
		// Pretend to do something
	}

	public function resend(): void {
		// Pretend to do something
	}
}