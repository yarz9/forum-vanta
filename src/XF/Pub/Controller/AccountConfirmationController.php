<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\EmailConfirmationPlugin;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Service\User\EmailConfirmationService;

class AccountConfirmationController extends AbstractController
{
	public function actionEmail(ParameterBag $params)
	{
		/** @var User $user */
		$user = $this->assertRecordExists(User::class, $params->user_id);

		/** @var EmailConfirmationService $emailConfirmation */
		$emailConfirmation = $this->service(EmailConfirmationService::class, $user);

		if (!$emailConfirmation->canTriggerConfirmation())
		{
			return $this->redirect($this->buildLink('index'));
		}

		$confirmationKey = $this->filter('c', 'str');
		if (!$emailConfirmation->isConfirmationVerified($confirmationKey))
		{
			return $this->error(\XF::phrase('your_email_could_not_be_confirmed_use_resend'));
		}

		$emailConfirmation->emailConfirmed();

		$viewParams = [];

		$preRegContent = $emailConfirmation->getPreRegContent();
		if ($preRegContent instanceof LinkableInterface)
		{
			$viewParams['preRegContentUrl'] = $preRegContent->getContentUrl();
		}

		if ($user->user_state == 'valid' && $this->session()->hasPreRegActionPending)
		{
			$this->session()->remove('hasPreRegActionPending');
		}

		return $this->view('XF:Register\Confirm', 'register_confirm', $viewParams);
	}

	public function actionResend()
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->redirect($this->buildLink('index'), '');
		}

		return $this->plugin(EmailConfirmationPlugin::class)->actionResend(
			$visitor,
			$this->buildLink('account-confirmation/resend'),
			['checkCaptcha' => true]
		);
	}

	public function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
	{
	}

	public function assertViewingPermissions($action)
	{
	}

	public function assertCorrectVersion($action)
	{
	}

	public function assertBoardActive($action)
	{
	}

	public function assertTfaRequirement($action)
	{
	}

	public function assertPolicyAcceptance($action)
	{
	}
}
