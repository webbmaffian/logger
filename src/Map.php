<?php
namespace Webbmaffian\Logger;
use ArrayAccess;

abstract class Map implements ArrayAccess {
	private $data = [];

	public function __construct(array $data) {
		$this->data = $data;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return $this->data[$offset] ?? null;
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

	public function entries(): array {
		return $this->data;
	}
}