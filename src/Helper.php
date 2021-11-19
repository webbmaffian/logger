<?php
namespace Webbmaffian\Logger;

class Helper {
	static public function string_to_trace(string $string): array {
		$result = [
			'message' => $string,
			'stacktrace' => []
		];

		if(preg_match('/^(?<message>.*)\s+(in)?\s+(?<path>[^:]+):(?<line>\d+)$/m', $string, $matches)) {
			$result['message'] = $matches['message'];
			$result['stacktrace'][] = [
				'note' => $matches['message'],
				'path' => $matches['path'],
				'line' => intval($matches['line'])
			];
		}

		if(preg_match_all('/#(?<index>\d+)\s*(?<path>[^:]+):\s*(?<callee>[^(]+)/', $string, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				$crumb = [];

				if(preg_match('/^(?<path>[^(]+)\((?<line>\d+)\)$/', $match['path'], $path_matches)) {
					$crumb['path'] = $path_matches['path'];
					$crumb['line'] = intval($path_matches['line']);
				}
				else {
					$crumb['note'] = $match['path'];
				}

				$crumb['callee'] = $match['callee'];
				$result['stacktrace'][] = $crumb;
			}
		}

		return $result;
	}
}