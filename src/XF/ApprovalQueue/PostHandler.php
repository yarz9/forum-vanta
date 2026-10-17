<?php

namespace XF\ApprovalQueue;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;
use XF\Service\Post\ApproverService;

/**
 * @extends AbstractHandler<Post>
 */
class PostHandler extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id, 'User'];
	}

	public function actionApprove(Post $post)
	{
		$approver = \XF::service(ApproverService::class, $post);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	public function actionDelete(Post $post)
	{
		$this->quickUpdate($post, 'message_state', 'deleted');
	}

	public function actionSpamClean(Post $post)
	{
		if (!$post->User)
		{
			return;
		}

		$this->_spamCleanInternal($post->User);
	}
}
