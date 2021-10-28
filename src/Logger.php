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
	private $persisted_context = [];
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

	/**
	 * Emergency: System is unusable, e.g. total datacenter outages.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function emergency(...$args): void {
		$this->log(EMERGENCY, $args);
	}

	/**
	 * Alert: Action must be taken immediately, e.g. hints that might lead to total datacenter outages.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function alert(...$args): void {
		$this->log(ALERT, $args);
	}

	/**
	 * Critical: Critical conditions, e.g. service, database or connection disruptions.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function critical(...$args): void {
		$this->log(CRITICAL, $args);
	}

	/**
	 * Error: Error conditions, e.g. fatal application errors.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function error(...$args): void {
		$this->log(ERROR, $args);
	}

	/**
	 * Warning: Warning conditions, e.g. non-fatal errors or possible security threats.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function warn(...$args): void {
		$this->log(WARNING, $args);
	}

	/**
	 * Notice: Normal but significant conditions, e.g. errors that was solved automatically but should be looked into, like undefined variables.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function notice(...$args): void {
		$this->log(NOTICE, $args);
	}

	/**
	 * Informational: Informational messages, e.g. events or audit logs.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function info(...$args): void {
		$this->log(INFORMATIONAL, $args);
	}

	/**
	 * Debug: Debug-level messages, e.g. step-by-step actions of events.
	 * @param Throwable|Index|Meta|Raw|mixed ...$args
	 */
	public function debug(...$args): void {
		$this->log(DEBUG, $args);
	}

	/**
	 * Return an Index object, to be used in logging or context. Non-scalar values will be dropped.
	 * @param string|array $key Key string, or key-value array
	 * @param string|int|float $value Value, or left out if supplied a key-value array
	 */
	public function index($key, $value = '#'): Index {
		if(is_array($key)) {
			$index = [];

			foreach($key as $k => $v) {
				if(!is_scalar($v)) continue;

				$index[$k] = trim((string)$value);
			}

			return new Index($index);
		}
		elseif(is_scalar($value)) {
			return new Index([$key => trim((string)$value)]);
		}

		return new Index();
	}

	/**
	 * Return a Meta object, to be used in logging or context.
	 * @param string|array $key Key string, or key-value array
	 * @param mixed $value Value, or left out if supplied a key-value array
	 */
	public function meta($key, $value = ''): Meta {
		if(is_object($key) && $key instanceof JsonSerializable) {
			return new Meta((array)($key->jsonSerialize()));
		}

		return new Meta(is_array($key) ? $key : [$key => $value]);
	}

	/**
	 * Return a Raw object, to be used in logging or context. Will set literal log entry parameter(s)
	 * @param string|array $key Key string, or key-value array
	 * @param mixed $value Value, or left out if supplied a key-value array
	 */
	public function raw($key, $value = null): Raw {
		return new Raw(is_array($key) ? $key : [$key => $value]);
	}

	/**
	 * Add a new context layer.
	 * @param Index|Meta|Raw ...$args
	 */
	public function set_context(Map ...$context): int {
		$this->context[] = $context;

		return count($this->context) - 1;
	}

	/**
	 * Reset latest context, or reset to a specific previous context.
	 * @param int $index (optional) Specific context index
	 */
	public function reset_context(?int $index = -1): void {
		array_splice($this->context, $index);
	}

	/**
	 * Add a new persistent context layer, which can't be reset.
	 * @param Index|Meta|Raw ...$args
	 */
	public function persist_context(Map ...$context): void {
		$this->persisted_context[] = $context;
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

		self::apply_context($this->persisted_context, $entry);
		self::apply_context($this->context, $entry);

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

	/**
	 * @param Map[] $contexts
	 * @param array &$entry
	 */
	static private function apply_context(array $contexts, array &$entry): void {
		foreach($contexts as $context) {
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
				$crumb = [];

				if(isset($item['file'])) {
					$crumb['path'] = $item['file'];
				}
	
				if(isset($item['line'])) {
					$crumb['line'] = $item['line'];
				}
	
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