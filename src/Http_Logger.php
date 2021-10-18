<?php
namespace Webbmaffian\Logger;
use Exception;

class Http_Logger extends File_Logger {
	const ENDPOINT = 'https://log.mafia.tools/api/v1/logs';
	const BATCH_SIZE = 100;

	public function resend(): void {
		if(!file_exists($this->options['path'])) return;

		$tmp_path = $this->options['path'] . '.tmp';

		rename($this->options['path'], $tmp_path);

		$file = fopen($tmp_path, 'r');
		
		try {
			$batch = [];

			while(false !== $json = fgets($file)) {
				$batch[] = $json;
	
				if(count($batch) >= self::BATCH_SIZE) {
					$this->send_batch($batch);
				}
			}
	
			$this->send_batch($batch);
		}

		// If we fail, save the remaining batch back to file
		catch(Exception $e) {
			foreach($batch as $json) {
				$this->send($json);
			}

			while(false !== $json = fgets($file)) {
				$this->send($json);
			}
		}

		fclose($file);
		unlink($tmp_path);
	}

	public function send_batch(array &$batch): void {
		if(empty($batch)) return;
		
		$ch = curl_init(self::ENDPOINT);

		# Setup request to send json via POST.
		curl_setopt($ch, CURLOPT_POSTFIELDS, implode("\n", $batch));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json-seq']);
		curl_setopt($ch, CURLOPT_USERPWD, $this->options['client_id'] . ':' . $this->options['client_secret']);

		# Return response instead of printing.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		# Send request.
		if(!curl_exec($ch)) {
			throw new Exception('Failed request');
		}

		if($curl_err = curl_errno($ch)) {
			throw new Exception('Error ' . $curl_err);
		}

		$info = curl_getinfo($ch);

		curl_close($ch);

		$http_status_code = (int)$info['http_code'];

		if($http_status_code !== 200) {
			throw new Exception('HTTP ' . $http_status_code);
		}

		$batch = [];
	}
}