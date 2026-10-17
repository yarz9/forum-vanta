<?php

namespace XF\NewsFeed;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<Post>
 */
class PostHandler extends AbstractHandler
{
	public function isPublishable(Entity $entity, $action)
	{
		if ($action == 'insert')
		{
			// first post inserts are handled by the thread
			return $entity->isFirstPost() ? false : true;
		}

		return true;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['User', 'Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}

	protected function addAttachmentsToContent($content)
	{
		return $this->addAttachments($content);
	}
}
