<?php

namespace XF\NewsFeed;

use XF\Entity\NewsFeed;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function is_array;

/**
 * @template T of Entity
 */
class AbstractHandler
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
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $entity, &$error = null)
	{
		if (method_exists($entity, 'canView'))
		{
			return $entity->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	/**
	 * @param T $content
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	public function canViewEntry(NewsFeed $newsFeed, Entity $content, &$error = null)
	{
		if ($newsFeed->action == 'reaction')
		{
			if (!isset($newsFeed->extra_data['reaction_id']))
			{
				throw new \LogicException("Reaction ID missing from news feed entry extra_data.");
			}

			$reactionId = $newsFeed->extra_data['reaction_id'];
			$reactionsCache = \XF::app()->container('reactions');

			if (!isset($reactionsCache[$reactionId]) || !$reactionsCache[$reactionId]['active'])
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * @param T $entity
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	public function contentIsVisible(Entity $entity, &$error = null)
	{
		if (method_exists($entity, 'isVisible'))
		{
			return $entity->isVisible($error);
		}

		if (\XF::$debugMode)
		{
			trigger_error("Could not determine content visibility; defaulted to true - please override", E_USER_WARNING);
		}

		return true;
	}

	/**
	 * @param T $entity
	 * @param string $action
	 *
	 * @return bool
	 */
	public function isPublishable(Entity $entity, $action)
	{
		return true;
	}

	/**
	 * @param string $action
	 *
	 * @return string
	 */
	public function getTemplateName($action)
	{
		return 'public:news_feed_item_' . $this->contentType . '_' . $action;
	}

	/**
	 * @param string $action
	 * @param T|null $content
	 *
	 * @return array{
	 *     newsFeed: NewsFeed,
	 *     user: \XF\Entity\User|null,
	 *     extra: array<mixed>,
	 *     content: T|null,
	 * }
	 */
	public function getTemplateData($action, NewsFeed $newsFeed, ?Entity $content = null)
	{
		if (!$content)
		{
			$content = $newsFeed->Content;
		}

		return [
			'newsFeed' => $newsFeed,
			'user' => $newsFeed->User,
			'extra' => $newsFeed->extra_data,
			'content' => $content,
		];
	}

	/**
	 * @param T|null $content
	 *
	 * @return string
	 */
	public function render(NewsFeed $newsFeed, ?Entity $content = null)
	{
		if (!$content)
		{
			$content = $newsFeed->Content;
			if (!$content)
			{
				return '';
			}
		}

		$action = $newsFeed->action;
		$template = $this->getTemplateName($action);
		$data = $this->getTemplateData($action, $newsFeed, $content);

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
	 * @param int|list<int> $ids
	 *
	 * @return T|\XF\Mvc\Entity\AbstractCollection<T>
	 */
	public function getContent($ids)
	{
		$isArray = is_array($ids);
		if (!$isArray)
		{
			$ids = [$ids];
		}

		$content = \XF::app()->findByContentType($this->contentType, $ids, $this->getEntityWith());
		$content = $this->addAttachmentsToContent($content);

		return $isArray ? $content : $content->first();
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<T> $content
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<T>
	 */
	protected function addAttachmentsToContent($content)
	{
		return $content;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<T> $content
	 * @param string $countKey
	 * @param string $relationKey
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<T>
	 */
	protected function addAttachments($content, $countKey = 'attach_count', $relationKey = 'Attachments')
	{
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		return $attachmentRepo->addAttachmentsToContent($content, $this->contentType, $countKey, $relationKey);
	}
}
