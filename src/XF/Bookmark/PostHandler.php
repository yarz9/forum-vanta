<?php

namespace XF\Bookmark;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<Post>
 */
class PostHandler extends AbstractHandler
{
	public function getContentUser(Entity $content)
	{
		if ($content->isFirstPost())
		{
			return $content->Thread->User;
		}
		else
		{
			return $content->User;
		}
	}

	public function getContentLink(Entity $content)
	{
		if ($content->isFirstPost())
		{
			return $content->Thread->getContentUrl(true);
		}
		else
		{
			return parent::getContentLink($content);
		}
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
