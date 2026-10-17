<?php

namespace XF\ApprovalQueue;

use XF\Entity\ApprovalQueue;
use XF\Entity\UsernameChange;
use XF\Mvc\Entity\Entity;
use XF\Repository\UsernameChangeRepository;
use XF\Service\User\UsernameChangeService;

/**
 * @extends AbstractHandler<UsernameChange>
 */
class UsernameChangeHandler extends AbstractHandler
{
	protected function canViewContent(Entity $content, &$error = null)
	{
		return true;
	}

	protected function canActionContent(Entity $content, &$error = null)
	{
		return \XF::visitor()->canApproveRejectUsernameChange();
	}

	public function getEntityWith()
	{
		return ['User'];
	}

	public function getTemplateData(ApprovalQueue $unapprovedItem)
	{
		$templateData = parent::getTemplateData($unapprovedItem);

		$change = $unapprovedItem->Content;

		$usernameChangeRepo = \XF::repository(UsernameChangeRepository::class);
		$changeFinder = $usernameChangeRepo->findUsernameChangesForList();

		$changes = $changeFinder
			->where('user_id', $change->user_id)
			->where('change_id', '<>', $change->change_id)
			->fetch(5);

		$templateData['previousChanges'] = $changes;

		return $templateData;
	}

	public function actionApprove(UsernameChange $usernameChange)
	{
		if (!$this->validateUsernameChangeForAction($usernameChange))
		{
			return;
		}

		$notify = $this->getInput('notify', $usernameChange->change_id);

		$changeService = \XF::app()->service(UsernameChangeService::class, $usernameChange);
		$changeService->setModeratorApproval($notify);
		$changeService->save();
	}

	public function actionReject(UsernameChange $usernameChange)
	{
		if (!$this->validateUsernameChangeForAction($usernameChange))
		{
			return;
		}

		$notify = $this->getInput('notify', $usernameChange->change_id);
		$reason = $this->getInput('reason', $usernameChange->change_id);

		$changeService = \XF::app()->service(UsernameChangeService::class, $usernameChange);
		$changeService->setModeratorRejection($notify, $reason);
		$changeService->save();
	}

	protected function validateUsernameChangeForAction(UsernameChange $usernameChange)
	{
		if ($usernameChange->change_state != 'moderated')
		{
			return false;
		}

		$user = $usernameChange->User;
		if (!$user)
		{
			// no user so we need to just get rid of this change log
			$usernameChange->delete();
			return false;
		}

		return true;
	}
}
