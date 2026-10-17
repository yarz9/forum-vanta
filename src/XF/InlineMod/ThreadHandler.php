<?php

namespace XF\InlineMod;

use XF\Entity\Thread;
use XF\InlineMod\Thread\ApplyPrefix;
use XF\InlineMod\Thread\ChangeType;
use XF\InlineMod\Thread\Delete;
use XF\InlineMod\Thread\Merge;
use XF\InlineMod\Thread\Move;
use XF\Mvc\Entity\Entity;
use XF\Service\Thread\ApproverService;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	use FeaturableTrait;

	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler(Delete::class);

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('undelete_threads'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var Thread $entity */
				if ($entity->discussion_state == 'deleted')
				{
					$entity->discussion_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('approve_threads'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Thread $entity */
				if ($entity->discussion_type != 'redirect' && $entity->discussion_state == 'moderated')
				{
					/** @var ApproverService $approver */
					$approver = \XF::service(ApproverService::class, $entity);
					$approver->setNotifyRunTime(1); // may be a lot happening
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('unapprove_threads'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var Thread $entity */
				if ($entity->discussion_type != 'redirect')
				{
					$entity->discussion_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['stick'] = $this->getSimpleActionHandler(
			\XF::phrase('stick_threads'),
			'canStickUnstick',
			function (Entity $entity)
			{
				/** @var Thread $entity */
				$entity->sticky = true;
				$entity->save();
			}
		);

		$actions['unstick'] = $this->getSimpleActionHandler(
			\XF::phrase('unstick_threads'),
			'canStickUnstick',
			function (Entity $entity)
			{
				/** @var Thread $entity */
				$entity->sticky = false;
				$entity->save();
			}
		);

		$actions['lock'] = $this->getSimpleActionHandler(
			\XF::phrase('lock_threads'),
			'canLockUnlock',
			function (Entity $entity)
			{
				if ($entity->discussion_type != 'redirect')
				{
					/** @var Thread $entity */
					$entity->discussion_open = false;
					$entity->save();
				}
			}
		);

		$actions['unlock'] = $this->getSimpleActionHandler(
			\XF::phrase('unlock_threads'),
			'canLockUnlock',
			function (Entity $entity)
			{
				if ($entity->discussion_type != 'redirect')
				{
					/** @var Thread $entity */
					$entity->discussion_open = true;
					$entity->save();
				}
			}
		);

		static::addPossibleFeatureActions(
			$this,
			$actions,
			\XF::phrase('feature_threads'),
			\XF::phrase('unfeature_threads'),
			'canFeatureUnfeature'
		);

		$actions['move'] = $this->getActionHandler(Move::class);
		$actions['merge'] = $this->getActionHandler(Merge::class);
		$actions['apply_prefix'] = $this->getActionHandler(ApplyPrefix::class);
		$actions['change_type'] = $this->getActionHandler(ChangeType::class);

		return $actions;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Forum', 'Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
