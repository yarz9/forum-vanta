<?php

namespace XF\InlineMod;

use XF\Entity\ProfilePost;
use XF\InlineMod\ProfilePost\Delete;
use XF\Mvc\Entity\Entity;
use XF\Service\ProfilePost\ApproverService;

/**
 * @extends AbstractHandler<ProfilePost>
 */
class ProfilePostHandler extends AbstractHandler
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
				/** @var ProfilePost $entity */
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
				/** @var ProfilePost $entity */
				if ($entity->message_state == 'moderated')
				{
					/** @var ApproverService $approver */
					$approver = \XF::service(ApproverService::class, $entity);
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('unapprove_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var ProfilePost $entity */
				if ($entity->message_state == 'visible')
				{
					$entity->message_state = 'moderated';
					$entity->save();
				}
			}
		);

		return $actions;
	}

	public function getEntityWith()
	{
		return ['ProfileUser', 'ProfileUser.Privacy'];
	}
}
