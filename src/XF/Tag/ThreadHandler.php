<?php

namespace XF\Tag;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

class ThreadHandler extends AbstractHandler
{
	public function getPermissionsFromContext(Entity $entity)
	{
		if ($entity instanceof Thread)
		{
			$thread = $entity;
			$forum = $thread->Forum;
		}
		else if ($entity instanceof Forum)
		{
			$thread = null;
			$forum = $entity;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be a thread or forum");
		}

		$visitor = \XF::visitor();

		if ($thread)
		{
			if ($thread->user_id == $visitor->user_id
				&& $visitor->hasNodePermission($thread->node_id, 'manageOthersTagsOwnThread')
			)
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = $visitor->hasNodePermission($thread->node_id, 'manageAnyTag');
			}

			$edit = $thread->canEditTags();
		}
		else
		{
			$removeOthers = false;
			$edit = $forum->canEditTags();
		}

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => $forum->min_tags,
		];
	}

	public function getContentVisibility(Entity $entity)
	{
		return $entity->discussion_state == 'visible';
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'thread' => $entity,
			'options' => $options,
		];
	}

	public function getEntityWith($forView = false)
	{
		$get = ['Forum'];
		if ($forView)
		{
			$get[] = 'User';
			$get[] = 'FirstPost';

			$visitor = \XF::visitor();
			$get[] = 'Forum.Node.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var Thread $entity */
		return $entity->canUseInlineModeration($error);
	}
}
