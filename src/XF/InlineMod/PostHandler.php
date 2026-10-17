<?php

namespace XF\InlineMod;

use XF\Entity\Post;
use XF\InlineMod\Post\Copy;
use XF\InlineMod\Post\Delete;
use XF\InlineMod\Post\Merge;
use XF\InlineMod\Post\Move;
use XF\Mvc\Entity\Entity;
use XF\Service\Post\ApproverService;

/**
 * @extends AbstractHandler<Post>
 */
class PostHandler extends AbstractHandler
{
	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler(Delete::class);

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('undelete_posts'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var Post $entity */
				if ($entity->message_state == 'deleted')
				{
					$entity->message_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('approve_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Post $entity */
				if ($entity->isFirstPost())
				{
					if ($entity->Thread->discussion_state == 'moderated')
					{
						$entity->Thread->discussion_state = 'visible';
						$entity->Thread->save();
					}
				}
				else if ($entity->message_state == 'moderated')
				{
					/** @var ApproverService $approver */
					$approver = \XF::service(ApproverService::class, $entity);
					$approver->setNotifyRunTime(1); // may be a lot happening
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('unapprove_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Post $entity */
				if ($entity->isFirstPost())
				{
					if ($entity->Thread->discussion_state == 'visible')
					{
						$entity->Thread->discussion_state = 'moderated';
						$entity->Thread->save();
					}
				}
				else if ($entity->message_state == 'visible')
				{
					$entity->message_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['move'] = $this->getActionHandler(Move::class);
		$actions['copy'] = $this->getActionHandler(Copy::class);
		$actions['merge'] = $this->getActionHandler(Merge::class);

		return $actions;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
