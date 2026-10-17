<?php

namespace XF\Api\Result;

use XF\Entity\ResultInterface;

use function count;

class ArrayResult implements ResultInterface, \Countable
{
	/**
	 * @var array
	 */
	protected $result;

	public function __construct(array $result)
	{
		$this->result = $result;
	}

	public function getResult()
	{
		return $this->result;
	}

	public function setResult(array $result)
	{
		$this->result = $result;
	}

	public function render()
	{
		return $this->result;
	}

	public function count(): int
	{
		return count($this->result);
	}
}
