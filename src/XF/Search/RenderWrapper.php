<?php

namespace XF\Search;

use XF\Mvc\Entity\Entity;
use XF\PreEscapedInterface;
use XF\Search\Data\AbstractData;

/**
 * @template T of Entity
 */
class RenderWrapper implements PreEscapedInterface
{
	/**
	 * @var AbstractData<T>
	 */
	protected $handler;

	/**
	 * @var T
	 */
	protected $result;

	/**
	 * @var array<string, mixed>
	 */
	protected $options;

	/**
	 * @param T $result
	 * @param array<string, mixed> $options
	 */
	public function __construct(AbstractData $handler, Entity $result, array $options = [])
	{
		$this->handler = $handler;
		$this->result = $result;
		$this->options = $options;
	}

	/**
	 * @param array<string, mixed> $extraOptions
	 *
	 * @return string
	 */
	public function render(array $extraOptions = [])
	{
		return $this->handler->renderResult($this->result, array_merge($this->options, $extraOptions));
	}

	/**
	 * @return string
	 */
	public function getPreEscapeType()
	{
		return 'html';
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, "Search render error: ");
			return '';
		}
	}

	/**
	 * @return AbstractData<T>
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * @return T
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public function mergeOptions(array $options)
	{
		$this->options = array_merge($this->options, $options);
	}
}
