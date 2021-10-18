<?php
namespace Webbmaffian\Logger;

class File_Logger extends Logger {
	const BUFFER_SIZE = 50;

	private $entries = [];

	public function __destruct() {
		$this->write();
	}

	private function write(): void {
		if(!empty($this->entries)) {
			$file = fopen($this->options['path'], 'a');

			foreach($this->entries as $json) {
				fwrite($file, $json . "\n");
			}

			fclose($file);
			$this->entries = [];
		}
	}

	protected function send(string $json): void {
		$this->entries[] = $json;

		if(count($this->entries) >= self::BUFFER_SIZE) {
			$this->write();
		}
	}

	public function resend(): void {
		if(get_class($this) === __CLASS__) return;
		if(!file_exists($this->options['path'])) return;

		$tmp_path = $this->options['path'] . '.tmp';

		rename($this->options['path'], $tmp_path);
		
		$file = fopen($tmp_path, 'r');
		
		while(false !== $json = fgets($file)) {
			$this->send($json);
		}

		unlink($tmp_path);
	}
}