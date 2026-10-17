<?php

namespace XF\Warning;

use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;

use function get_class;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @param T $entity
	 *
	 * @return string|\Stringable
	 */
	abstract public function getStoredTitle(Entity $entity);

	/**
	 * @param string $title
	 *
	 * @return string|\Stringable
	 */
	abstract public function getDisplayTitle($title);

	/**
	 * @param T $entity
	 *
	 * @return string
	 */
	abstract public function getContentForConversation(Entity $entity);

	/**
	 * @param T $entity
	 *
	 * @return User
	 */
	abstract public function getContentUser(Entity $entity);

	/**
	 * @param T $entity
	 * @param string|\Stringable|null &$error
	 *
	 * @return bool
	 */
	abstract public function canViewContent(Entity $entity, &$error = null);

	/**
	 * @param T $entity
	 */
	abstract public function onWarning(Entity $entity, Warning $warning);

	/**
	 * @param T $entity
	 */
	abstract public function onWarningRemoval(Entity $entity, Warning $warning);

	/**
	 * @param T $entity
	 * @param bool $canonical
	 *
	 * @return string
	 */
	public function getContentUrl(Entity $entity, $canonical = false)
	{
		if ($entity instanceof LinkableInterface)
		{
			return $entity->getContentUrl($canonical);
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($entity)
			. ' or override ' . get_class($this) . '::getContentUrl'
		);
	}

	/**
	 * @param T $entity
	 *
	 * @return array<string, bool>
	 */
	public function getAvailableContentActions(Entity $entity)
	{
		return [
			'public' => $this->canWarnPublicly($entity),
			'delete' => $this->canDeleteContent($entity),
		];
	}

	/**
	 * @param T $entity
	 * @param string $action
	 * @param array<string, mixed> $options
	 */
	public function takeContentAction(Entity $entity, $action, array $options)
	{
		// do nothing by default since nothing is supported
	}

	/**
	 * @param T $entity
	 *
	 * @return bool
	 */
	protected function canWarnPublicly(Entity $entity)
	{
		return false;
	}

	/**
	 * @param T $entity
	 *
	 * @return bool
	 */
	protected function canDeleteContent(Entity $entity)
	{
		return false;
	}

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param int|list<int> $id
	 *
	 * @return T|\XF\Mvc\Entity\AbstractCollection<T>
	 */
	public function getContent($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id);
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * @return list<string>
	 */
	public static function getWebhookEvents(): array
	{
		return ['warn'];
	}
}
