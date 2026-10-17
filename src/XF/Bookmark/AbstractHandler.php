<?php

namespace XF\Bookmark;

use XF\Entity\BookmarkItem;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
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
	 * @return User|null
	 */
	public function getContentUser(Entity $content)
	{
		return $content->User ?? null;
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
			return $content->getContentTitle();
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentTitle'
		);
	}

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	public function getContentLink(Entity $content)
	{
		if ($content instanceof LinkableInterface)
		{
			return $content->getContentUrl(true);
		}

		return \XF::app()->router('public')->buildLink('canonical:' . $this->getContentRoute($content), $content);
	}

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	public function getContentRoute(Entity $content)
	{
		if ($content instanceof LinkableInterface)
		{
			return $content->getContentPublicRoute();
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentRoute'
		);
	}

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	public function getEditLink(Entity $content)
	{
		return \XF::app()->router('public')->buildLink($this->getContentRoute($content) . '/bookmark', $content);
	}

	/**
	 * @param T $content
	 *
	 * @return string
	 */
	public function getDeleteLink(Entity $content)
	{
		return \XF::app()->router('public')->buildLink($this->getContentRoute($content) . '/bookmark', $content, ['delete' => 1]);
	}

	/**
	 * @param T $content
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $content, &$error = null)
	{
		if (method_exists($content, 'canView'))
		{
			return $content->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	/**
	 * @param T|null $content
	 *
	 * @return array{
	 *     bookmark: BookmarkItem,
	 *     user: \XF\Entity\User|null,
	 *     content: T|null,
	 * }
	 */
	protected function getDefaultTemplateData(BookmarkItem $bookmark, ?Entity $content = null)
	{
		if (!$content)
		{
			$content = $bookmark->Content;
		}

		return [
			'bookmark' => $bookmark,
			'user' => $bookmark->User,
			'content' => $content,
		];
	}

	/**
	 * @return string|null
	 */
	public function getCustomIconTemplateName()
	{
		return null;
	}

	/**
	 * @param T|null $content
	 *
	 * @return array{
	 *     bookmark: BookmarkItem,
	 *     user: \XF\Entity\User|null,
	 *     content: T|null,
	 * }
	 */
	public function getCustomIconTemplateData(BookmarkItem $bookmark, ?Entity $content = null)
	{
		return $this->getDefaultTemplateData($bookmark, $content);
	}

	/**
	 * @param T|null $content
	 *
	 * @return string
	 */
	public function renderCustomIcon(BookmarkItem $bookmark, ?Entity $content = null)
	{
		if (!$this->getCustomIconTemplateName())
		{
			return '';
		}

		if (!$content)
		{
			$content = $bookmark->Content;
			if (!$content)
			{
				return '';
			}
		}

		$template = $this->getCustomIconTemplateName();
		$data = $this->getCustomIconTemplateData($bookmark, $content);

		return \XF::app()->templater()->renderTemplate($template, $data);
	}

	/**
	 * @return string
	 */
	public function getItemTemplateName()
	{
		return 'public:bookmark_item_' . $this->contentType;
	}

	/**
	 * @param T|null $content
	 *
	 * @return array{
	 *     bookmark: BookmarkItem,
	 *     user: \XF\Entity\User|null,
	 *     content: T|null,
	 * }
	 */
	public function getItemTemplateData(BookmarkItem $bookmark, ?Entity $content = null)
	{
		return $this->getDefaultTemplateData($bookmark, $content);
	}

	/**
	 * @param T|null $content
	 *
	 * @return string
	 */
	public function renderMessageFallback(BookmarkItem $bookmark, ?Entity $content = null)
	{
		if (!$content)
		{
			$content = $bookmark->Content;
			if (!$content)
			{
				return '';
			}
		}

		$template = $this->getItemTemplateName();
		$data = $this->getItemTemplateData($bookmark, $content);

		return \XF::app()->templater()->renderTemplate($template, $data);
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
		return ['bookmark'];
	}
}
