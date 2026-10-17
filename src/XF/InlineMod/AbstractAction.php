<?php

namespace XF\InlineMod;

use XF\App;
use XF\Http\Request;
use XF\Http\Response;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;

/**
 * @template T of Entity
 */
abstract class AbstractAction
{
	/**
	 * @var AbstractHandler<T>
	 */
	protected $handler;

	/**
	 * @var AbstractCollection<T>
	 */
	protected $entities;

	/**
	 * @var string|null
	 */
	protected $returnUrl;

	/**
	 * @return string
	 */
	abstract public function getTitle();

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 * @param mixed $error
	 *
	 * @return bool
	 */
	abstract protected function canApplyToEntity(
		Entity $entity,
		array $options,
		&$error = null
	);

	/**
	 * @param T $entity
	 * @param array<mixed> $options
	 */
	abstract protected function applyToEntity(Entity $entity, array $options);

	/**
	 * @param AbstractHandler<T> $handler
	 */
	public function __construct(AbstractHandler $handler)
	{
		$this->handler = $handler;
	}

	/**
	 * @return array<mixed>
	 */
	public function getBaseOptions()
	{
		return [];
	}

	/**
	 * @param AbstractCollection<T> $entities
	 *
	 * @return AbstractReply|null
	 */
	public function renderForm(
		AbstractCollection $entities,
		Controller $controller
	)
	{
		return null;
	}

	/**
	 * @param AbstractCollection<T> $entities
	 *
	 * @return array<mixed>
	 */
	public function getFormOptions(
		AbstractCollection $entities,
		Request $request
	)
	{
		return [];
	}

	/**
	 * @param array<mixed> $options
	 *
	 * @return array<mixed>
	 */
	protected function standardizeOptions(array $options)
	{
		return array_merge($this->getBaseOptions(), $options);
	}

	/**
	 * @param AbstractCollection<T> $entities
	 * @param array<mixed> $options
	 * @param mixed $error
	 *
	 * @return bool
	 */
	public function canApply(
		AbstractCollection $entities,
		array $options = [],
		&$error = null
	)
	{
		$options = $this->standardizeOptions($options);
		return $this->canApplyInternal($entities, $options, $error);
	}

	/**
	 * @param AbstractCollection<T> $entities
	 * @param array<mixed> $options
	 * @param mixed $error
	 *
	 * @return bool
	 */
	protected function canApplyInternal(
		AbstractCollection $entities,
		array $options,
		&$error
	)
	{
		foreach ($entities AS $entity)
		{
			if (!$this->handler->canViewContent($entity, $error))
			{
				return false;
			}
			if (!$this->canApplyToEntity($entity, $options, $error))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param AbstractCollection<T> $entities
	 * @param array<mixed> $options
	 */
	public function apply(AbstractCollection $entities, array $options = [])
	{
		$options = $this->standardizeOptions($options);
		$this->applyInternal($entities, $options);
	}

	/**
	 * @param AbstractCollection<T> $entities
	 * @param array<mixed> $options
	 */
	protected function applyInternal(
		AbstractCollection $entities,
		array $options
	)
	{
		foreach ($entities AS $entity)
		{
			$this->applyToEntity($entity, $options);
		}
	}

	/**
	 * @return string|null
	 */
	public function getReturnUrl()
	{
		return $this->returnUrl;
	}

	/**
	 * @param AbstractCollection<T> $entities
	 */
	public function postApply(
		AbstractCollection $entities,
		AbstractReply &$reply,
		Response $response
	)
	{
		$this->handler->clearCookie($response);
	}

	/**
	 * @return App
	 */
	protected function app()
	{
		return $this->handler->app();
	}
}
