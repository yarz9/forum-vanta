<?php

namespace XF\Service\User;

use XF\Behavior\ChangeLoggable;
use XF\Mvc\Entity\Entity;
use XF\Repository\IpRepository;
use XF\Repository\PreRegActionRepository;

class EmailConfirmationService extends AbstractConfirmationService
{
	/**
	 * @var Entity|null
	 */
	protected $preRegContent;

	public function getType()
	{
		return 'email';
	}

	public function canTriggerConfirmation(&$error = null)
	{
		if (!$this->user->isAwaitingEmailConfirmation())
		{
			$error = \XF::phrase('your_account_does_not_require_confirmation');
			return false;
		}

		if (!$this->user->email)
		{
			$error = \XF::phrase('this_account_cannot_be_confirmed_without_email_address');
			return false;
		}

		return true;
	}

	public function emailConfirmed()
	{
		$user = $this->user;
		if (!$user->isAwaitingEmailConfirmation())
		{
			return false;
		}

		$originalUserState = $user->user_state;

		if ($user->user_state == 'email_confirm' && $user->register_date > (\XF::$time - 30 * 86400))
		{
			// don't log when changing from initial confirm state for new users as it creates a lot of noise
			$user->getBehavior(ChangeLoggable::class)->setOption('enabled', false);
		}

		$this->advanceUserState();
		$user->save();

		if ($this->confirmation->exists())
		{
			$this->confirmation->delete();
		}

		$this->triggerExtraActions($originalUserState);

		return true;
	}

	public function getPreRegContent()
	{
		return $this->preRegContent;
	}

	protected function advanceUserState()
	{
		$user = $this->user;

		switch ($user->user_state)
		{
			case 'email_confirm':
				if ($this->app->options()->registrationSetup['moderation'])
				{
					$user->user_state = 'moderated';
					break;
				}
				// no break

			case 'email_confirm_edit': // this is a user editing email, never send back to moderation
			case 'moderated':
				$user->user_state = 'valid';
				break;
		}
	}

	protected function triggerExtraActions($originalUserState)
	{
		$user = $this->user;

		if ($originalUserState == 'email_confirm' && $user->user_state == 'valid')
		{
			/** @var RegistrationCompleteService $regComplete */
			$regComplete = $this->service(RegistrationCompleteService::class, $user);
			$regComplete->triggerCompletionActions();
			$this->preRegContent = $regComplete->getPreRegContent();
		}
		else
		{
			/** @var PreRegActionRepository $preRegActionRepo */
			$preRegActionRepo = $this->repository(PreRegActionRepository::class);
			$preRegActionRepo->completeUserActionIfPossible($user);
		}

		$this->repository(IpRepository::class)->logIp(
			$user->user_id,
			\XF::app()->request()->getIp(),
			'user',
			$user->user_id,
			'email_confirm'
		);
	}
}
