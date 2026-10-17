<?php

namespace XF\ContentVote;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;

class PostHandler extends AbstractHandler
{
	public function isCountedForContentUser(Entity $entity)
	{
		/** @var Post $entity */
		return $entity->isVisible();
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();
		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
