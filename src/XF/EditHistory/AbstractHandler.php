<?php

namespace XF\EditHistory;

use XF\Entity\EditHistory;
use XF\Entity\LinkableInterface;
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
	 * @param T $content
	 *
	 * @return bool
	 */
	abstract public function canViewHistory(Entity $content);

	/**
	 * @param T $content
	 *
	 * @return bool
	 */
	abstract public function canRevertContent(Entity $content);

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	abstract public function getContentText(Entity $content);

	/**
	 * @param T $content
	 *
	 * @return list<array{value: string, href: string}>
	 */
	abstract public function getBreadcrumbs(Entity $content);

	/**
	 * @param T $content
	 */
	abstract public function revertToVersion(Entity $content, EditHistory $history, ?EditHistory $previous = null);

	/**
	 * @param string $text
	 * @param T|null $content
	 *
	 * @return string
	 */
	abstract public function getHtmlFormattedContent($text, ?Entity $content = null);

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	public function getContentLink(Entity $content)
	{
		if ($content instanceof LinkableInterface)
		{
			return $content->getContentUrl();
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentLink'
		);
	}

	/**
	 * @param T $content
	 *
	 * @return string|\Stringable
	 */
	public function getContentTitle(Entity $content)
	{
		if ($content instanceof LinkableInterface)
		{
			return $content->getContentTitle('edit_history');
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentTitle'
		);
	}

	/**
	 * @param T $content
	 *
	 * @return int
	 */
	public function getEditCount(Entity $content)
	{
		return $content->edit_count;
	}

	/**
	 * @return string
	 */
	public function getSectionContext()
	{
		return '';
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
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}
}
