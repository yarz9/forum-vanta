<?php

namespace XF\InlineMod;

use XF\Mvc\Entity\Entity;

use function is_string;

/**
 * @template T of Entity
 * @extends AbstractAction<T>
 */
class SimpleAction extends AbstractAction
{
	/**
	 * @var string|\Closure(): string
	 */
	protected $title;

	/**
	 * @var string|\Closure(T, array, mixed): bool|true
	 */
	protected $canApply;

	/**
	 * @var \Closure(T, array): void
	 */
	protected $apply;

	/**
	 * @param AbstractHandler<T> $handler
	 * @param string|\Closure(): string $title
	 * @param string|\Closure(T, array, mixed): bool|true $canApply
	 * @param \Closure(T, array): void $apply
	 */
	public function __construct(
		AbstractHandler $handler,
		$title,
		$canApply,
		\Closure $apply
	)
	{
		parent::__construct($handler);

		$this->setTitle($title);
		$this->setCanApply($canApply);
		$this->setApply($apply);
	}

	/**
	 * @param string|\Closure(): string $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		if ($this->title instanceof \Closure)
		{
			$title = $this->title;
			return $title();
		}

		return $this->title;
	}

	/**
	 * @param string|\Closure(T, array, mixed): bool|true $canApply
	 */
	public function setCanApply($canApply)
	{
		$this->canApply = $canApply;
	}

	/**
	 * @param \Closure(T, array): void $apply
	 */
	public function setApply(\Closure $apply)
	{
		$this->apply = $apply;
	}

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 * @param mixed $error
	 *
	 * @return bool
	 */
	protected function canApplyToEntity(
		Entity $entity,
		array $options,
		&$error = null
	)
	{
		$canApply = $this->canApply;

		if (is_string($canApply))
		{
			return $entity->{$canApply}($error);
		}

		if ($canApply instanceof \Closure)
		{
			return $canApply($entity, $options, $error);
		}

		if ($canApply === true)
		{
			return true;
		}

		throw new \InvalidArgumentException(
			'canApply must be a string for a method, a closure or true'
		);
	}

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 */
	protected function applyToEntity(Entity $entity, array $options)
	{
		$apply = $this->apply;

		if ($apply instanceof \Closure)
		{
			$apply($entity, $options);
			return;
		}

		throw new \InvalidArgumentException(
			'Apply must be overridden (with a closure)'
		);
	}
}
