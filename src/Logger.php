<?php
namespace Webbmaffian\Logger;
use Throwable;
use JsonSerializable;

const EMERGENCY = 0;
const ALERT = 1;
const CRITICAL = 2;
const ERROR = 3;
const WARNING = 4;
const NOTICE = 5;
const INFORMATIONAL = 6;
const DEBUG = 7;

abstract class Logger {
	private $last_timestamp = null;
	private $context = [];
	protected $options = [];


	public function __construct(array $options = []) {
		$this->options = $options;

		if(!isset($this->options['stacktrace_initializer']) || !is_callable($this->options['stacktrace_initializer'])) {
			$this->options['stacktrace_initializer'] = function($item) {
				return (empty($item['file']) || $item['file'] !== __FILE__);
			};
		}

		if(!isset($this->options['message_handler']) || !is_callable($this->options['message_handler'])) {
			$this->options['message_handler'] = function($arg, &$message_parts, &$entry) {
				return false;
			};
		}
	}


	public function emergency(...$args): void {
		$this->log(EMERGENCY, $args);
	}

	public function alert(...$args): void {
		$this->log(ALERT, $args);
	}

	public function critical(...$args): void {
		$this->log(CRITICAL, $args);
	}

	public function error(...$args): void {
		$this->log(ERROR, $args);
	}

	public function warning(...$args): void {
		$this->log(WARNING, $args);
	}

	public function notice(...$args): void {
		$this->log(NOTICE, $args);
	}

	public function informational(...$args): void {
		$this->log(INFORMATIONAL, $args);
	}

	public function debug(...$args): void {
		$this->log(DEBUG, $args);
	}

	/**
	 * Alias for Logger::emergency()
	 */
	public function fatal(...$args): void {
		$this->log(EMERGENCY, $args);
	}

	/**
	 * Alias for Logger::critical()
	 */
	public function crit(...$args): void {
		$this->log(CRITICAL, $args);
	}

	/**
	 * Alias for Logger::warning()
	 */
	public function warn(...$args): void {
		$this->log(WARNING, $args);
	}

	/**
	 * Alias for Logger::informational()
	 */
	public function info(...$args): void {
		$this->log(INFORMATIONAL, $args);
	}

	public function index($key, $value = '#'): Index {
		return new Index(is_array($key) ? $key : [$key => $value]);
	}

	public function meta($key, $value = ''): Meta {
		if(is_object($key) && $key instanceof JsonSerializable) {
			return new Meta((array)($key->jsonSerialize()));
		}

		return new Meta(is_array($key) ? $key : [$key => $value]);
	}

	public function raw($key, $value = null): Raw {
		return new Raw(is_array($key) ? $key : [$key => $value]);
	}

	public function set_context(...$args): int {
		$this->context[] = array_map(function($arg) {
			return (is_array($arg) ? new Meta($arg) : $arg);
		}, $args);

		return count($this->context) - 1;
	}

	public function reset_context(?int $index = -1): void {
		array_splice($this->context, $index);
	}

	private function log(int $severity, array $args): void {
		$timestamp = microtime(true);
		
		$entry = [
			'app' => 'php',
			'timestamp' => intval($timestamp * 1000),
			'host' => strtok($_SERVER['HTTP_HOST'] ?? '', ':') ?: 'localhost',
			'severity' => $severity,
			'facility' => 1,
			'indices' => [],
			'meta' => []
		];

		if(isset($_SERVER['REQUEST_METHOD'])) {
			$entry['httpMethod'] = $_SERVER['REQUEST_METHOD'];
		}

		if(is_null($this->last_timestamp) && isset($_SERVER['REQUEST_TIME_FLOAT'])) {
			$this->last_timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
		}

		if(!is_null($this->last_timestamp)) {
			$entry['duration'] = intval(($timestamp - $this->last_timestamp) * 1000000);
		}

		$this->last_timestamp = $timestamp;

		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$entry['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		elseif(!empty($_SERVER['REMOTE_ADDR'])) {
			$entry['ip'] = $_SERVER['REMOTE_ADDR'];
		}

		if(!empty($_SERVER['REQUEST_URI'])) {
			$entry['path'] = $_SERVER['REQUEST_URI'];
		}

		if(!empty($_SERVER['HTTP_USER_AGENT'])) {
			$entry['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		$stacktrace = null;
		$message_parts = [];

		foreach($this->context as $context) {
			foreach($context as $arg) {
				if($arg instanceof Index) {
					$entry['indices'] = array_merge($entry['indices'], $arg->entries());
				}
				elseif($arg instanceof Meta) {
					$entry['meta'] = array_merge($entry['meta'], $arg->entries());
				}
				elseif($arg instanceof Raw) {
					$entry = array_merge($entry, $arg->entries());
				}
			}
		}

		foreach($args as $arg) {
			if($this->options['message_handler']($arg, $message_parts, $entry)) {
				continue;
			}

			if($arg instanceof Throwable) {
				$message_parts[] = $arg->getMessage();
				$stacktrace = self::get_stacktrace($arg);
			}
			elseif($arg instanceof Index) {
				$entry['indices'] = array_merge($entry['indices'], $arg->entries());
			}
			elseif($arg instanceof Meta) {
				$entry['meta'] = array_merge($entry['meta'], $arg->entries());
			}
			elseif($arg instanceof Raw) {
				$entry = array_merge($entry, $arg->entries());
			}
			elseif(is_scalar($arg) && !is_bool($arg)) {
				$message_parts[] = $arg;
			}
			else {
				$message_parts[] = var_export($arg, true);
			}
		}

		if(count($message_parts) > 1) {
			$message = sprintf(...$message_parts);

			if($message == $message_parts[0]) {
				$message = implode(' ', $message_parts);
			}
		}
		elseif(!empty($message_parts)) {
			$message = $message_parts[0];
		}
		else {
			$message = '(no message)';
		}

		$entry['message'] = $message;

		if(!array_key_exists('stacktrace', $entry)) {
			if(!$stacktrace) {
				$stacktrace = $this->create_stacktrace();
			}

			$entry['stacktrace'] = $stacktrace;
		}
		elseif(is_null($entry['stacktrace'])) {
			unset($entry['stacktrace']);
		}

		if(empty($entry['indices'])) unset($entry['indices']);
		if(empty($entry['meta'])) unset($entry['meta']);

		$this->send(json_encode($entry));
	}

	static private function get_stacktrace(Throwable $e): array {
		$stacktrace = [
			[
				'path' => $e->getFile(),
				'line' => $e->getLine(),
				'note' => $e->getMessage()
			]
		];

		if($previous_exception = $e->getPrevious()) {
			array_push($stacktrace, ...self::get_stacktrace($previous_exception));
		}
		else {
			foreach($e->getTrace() as $item) {
				$crumb = [
					'path' => $item['file'],
					'line' => $item['line']
				];
	
				if(!empty($item['function'])) {
					$crumb['callee'] = $item['function'];
				}
	
				$stacktrace[] = $crumb;
			}
		}

		return $stacktrace;
	}

	private function create_stacktrace(): array {
		$raw = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$initiated = false;
		$stacktrace = [];

		foreach($raw as $item) {
			if(!$initiated) {
				if(!$this->options['stacktrace_initializer']($item)) {
					continue;
				}

				$initiated = true;
			}

			$crumb = [];

			if(isset($item['file'])) {
				$crumb['path'] = $item['file'];
			}

			if(isset($item['line'])) {
				$crumb['line'] = $item['line'];
			}

			if($callee = ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? '')) {
				$crumb['callee'] = $callee;
			}

			$stacktrace[] = $crumb;
		}

		return $stacktrace;
	}

	abstract protected function send(string $json): void;
	abstract public function resend(): void;
}