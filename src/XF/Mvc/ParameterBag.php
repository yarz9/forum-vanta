<?php

namespace XF\Mvc;

use function array_key_exists, count;

class ParameterBag implements \ArrayAccess, \Countable
{
	protected $params;

	public function __construct(array $params = [])
	{
		$this->params = $params;
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($key)
	{
		return $this->params[$key] ?? null;
	}

	public function __get($key)
	{
		return $this->offsetGet($key);
	}

	public function get($key, $fallback = null)
	{
		return array_key_exists($key, $this->params) ? $this->params[$key] : $fallback;
	}

	public function offsetSet($key, $value): void
	{
		$this->params[$key] = $value;
	}

	public function __set($key, $value)
	{
		$this->offsetSet($key, $value);
	}

	public function offsetExists($key): bool
	{
		return array_key_exists($key, $this->params);
	}

	public function offsetUnset($key): void
	{
		unset($this->params[$key]);
	}

	public function params()
	{
		return $this->params;
	}

	public function count(): int
	{
		return count($this->params);
	}
}
