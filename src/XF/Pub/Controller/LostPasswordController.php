<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\LoginPlugin;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Service\User\PasswordResetService;
use XF\Validator\Email;

class LostPasswordController extends AbstractController
{
	public function actionIndex()
	{
		if (\XF::visitor()->user_id)
		{
			return $this->redirect($this->buildLink('index'), '');
		}

		if ($this->isPost())
		{
			$email = $this->filter('email', 'str');

			$validator = $this->app->validator(Email::class);
			$email = $validator->coerceValue($email);
			if (!$validator->isValid($email, $error))
			{
				return $this->error(\XF::phrase('please_enter_valid_email'));
			}

			if ($this->options()->lostPasswordCaptcha && !$this->captchaIsValid())
			{
				return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
			}

			$user = $this->em()->findOne(User::class, ['email' => $email]);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_member_not_found'));
			}

			/** @var PasswordResetService $passwordConfirmation */
			$passwordConfirmation = $this->service(PasswordResetService::class, $user);
			if (!$passwordConfirmation->canTriggerConfirmation($error))
			{
				return $this->error($error);
			}

			$passwordConfirmation->triggerConfirmation();

			return $this->redirect($this->buildLink('lost-password/requested'));
		}
		else
		{
			if ($this->options()->lostPasswordCaptcha)
			{
				$this->assertCaptchaCookieConsent();
			}
			return $this->view('XF:LostPassword\Index', 'lost_password');
		}
	}

	public function actionRequested()
	{
		return $this->message(\XF::phrase('password_reset_request_has_been_emailed_to_you'));
	}

	public function actionConfirm(ParameterBag $params)
	{
		/** @var User $user */
		$user = $this->assertRecordExists(User::class, $params->user_id);

		/** @var PasswordResetService $lostPassword */
		$lostPassword = $this->service(PasswordResetService::class, $user);

		$confirmationKey = $this->filter('c', 'str');
		if (!$lostPassword->isConfirmationVerified($confirmationKey))
		{
			return $this->error(\XF::phrase('your_action_could_not_be_confirmed_request_new'));
		}

		if ($this->isPost())
		{
			$passwords = $this->filter([
				'password' => 'str',
				'password_confirm' => 'str',
			]);

			if (!$passwords['password'])
			{
				return $this->error(\XF::phrase('please_enter_valid_password'));
			}

			if (!$passwords['password_confirm'] || $passwords['password'] !== $passwords['password_confirm'])
			{
				return $this->error(\XF::phrase('passwords_did_not_match'));
			}

			$lostPassword->resetLostPassword($passwords['password']);

			if (!\XF::visitor()->user_id)
			{
				/** @var LoginPlugin $loginPlugin */
				$loginPlugin = $this->plugin(LoginPlugin::class);
				$loginPlugin->triggerIfTfaConfirmationRequired(
					$user,
					$this->buildLink('login/two-step', null, [
						'_xfRedirect' => $this->buildLink('index'),
					])
				);

				$this->session()->changeUser($user);
			}

			return $this->redirect($this->buildLink('index'), \XF::phrase('your_password_has_been_reset'));
		}
		else
		{
			$viewParams = [
				'user' => $user,
				'c' => $confirmationKey,
			];
			return $this->view('XF:LostPassword\Confirm', 'lost_password_confirm', $viewParams);
		}
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
