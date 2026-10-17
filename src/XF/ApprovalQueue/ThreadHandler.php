<?php

namespace XF\ApprovalQueue;

use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;
use XF\Service\Thread\ApproverService;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Forum', 'Forum.Node.Permissions|' . $visitor->permission_combination_id, 'FirstPost', 'User'];
	}

	public function actionApprove(Thread $thread)
	{
		$approver = \XF::service(ApproverService::class, $thread);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	public function actionDelete(Thread $thread)
	{
		$this->quickUpdate($thread, 'discussion_state', 'deleted');
	}

	public function actionSpamClean(Thread $thread)
	{
		if (!$thread->User)
		{
			return;
		}

		$this->_spamCleanInternal($thread->User);
	}
}
