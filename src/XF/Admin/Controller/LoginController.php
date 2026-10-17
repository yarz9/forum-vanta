<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\LoginPlugin;
use XF\ControllerPlugin\LoginTfaResultPlugin;
use XF\Entity\Admin;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Repository\IpRepository;
use XF\Service\Passkey\ManagerService;
use XF\Service\User\LoginService;
use XF\Session\Session;
use XF\Util\File;

class LoginController extends AbstractController
{
	public function actionForm()
	{
		$redirect = $this->getDynamicRedirectIfNot($this->buildLink('login'));

		$passkey = $this->service(ManagerService::class);
		$passkey->saveStateToSession($this->session());

		$viewParams = [
			'redirect' => $redirect,
			'passkey' => $passkey,
		];
		return $this->view('XF:Login\Form', 'login_form', $viewParams);
	}

	public function actionLogin()
	{
		$this->assertPostOnly();

		$redirect = $this->getDynamicRedirectIfNot($this->buildLink('login'));

		if (\XF::visitor()->user_id)
		{
			return $this->redirect($redirect);
		}

		$webAuthnPayload = $this->filter('webauthn_payload', 'json-array');
		if ($webAuthnPayload)
		{
			$passkey = $this->service(ManagerService::class, $this->session());
			if (!$passkey->validate($this->request(), $error))
			{
				$passkey->clearStateFromSession($this->session());
				return $this->error($error);
			}

			/** @var LoginPlugin $loginPlugin */
			$loginPlugin = $this->plugin(LoginPlugin::class);
			$loginPlugin->completeLogin($passkey->getPasskeyUser(), true);

			return $this->redirect($redirect, '');
		}

		$input = $this->filter([
			'login' => 'str',
			'password' => 'str',
		]);

		$ip = $this->request->getIp();

		/** @var LoginService $loginService */
		$loginService = $this->service(LoginService::class, $input['login'], $ip);
		if ($loginService->isLoginLimited($limitType))
		{
			return $this->error(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
		}

		$user = $loginService->validate($input['password'], $error);
		if (!$user)
		{
			return $this->error($error);
		}

		if (!$user->is_admin)
		{
			return $this->error(\XF::phrase('your_account_does_not_have_admin_privileges'));
		}

		if ($user->security_lock)
		{
			return $this->error(\XF::phrase('your_account_is_currently_security_locked'));
		}

		/** @var LoginPlugin $loginPlugin */
		$loginPlugin = $this->plugin(LoginPlugin::class);
		$loginPlugin->triggerIfTfaConfirmationRequired(
			$user,
			$this->buildLink('login/two-step', null, [
				'_xfRedirect' => $redirect,
			])
		);

		if (empty($user->Option->use_tfa)
			&& \XF::config('enableTfa')
			&& ($this->options()->adminRequireTfa || $user->hasPermission('general', 'requireTfa'))
		)
		{
			return $this->error(\XF::phrase('you_must_enable_two_step_access_control_panel', [
				'link' => $this->app->router('public')->buildLink('account/two-step'),
			]));
		}

		$this->completeLogin($user);

		// TODO: POST handling?

		return $this->redirect($redirect, '');
	}

	public function actionTwoStep()
	{
		/** @var LoginPlugin $loginPlugin */
		$loginPlugin = $this->plugin(LoginPlugin::class);
		$redirect = $this->getDynamicRedirectIfNot($this->buildLink('login'));

		$result = $loginPlugin->runTfaCheck($redirect);
		switch ($result->getResult())
		{
			case LoginTfaResultPlugin::RESULT_ERROR:
				return $this->error($result->getError());

			case LoginTfaResultPlugin::RESULT_FORM:
				$viewParams = $result->getFormParams();
				$viewParams['trustChecked'] = $this->request->getCookie('user');
				return $this->view('XF:Login\TwoStep', 'login_two_step', $viewParams);

			case LoginTfaResultPlugin::RESULT_SKIPPED:
				return $this->redirect($result->getRedirect(), '');

			case LoginTfaResultPlugin::RESULT_SUCCESS:
				$this->completeLogin($result->getUser());
				return $this->redirect($result->getRedirect(), '');

			default:
				return $this->error(\XF::phrase('requested_page_not_found'));
		}
	}

	protected function completeLogin(User $user)
	{
		$this->session()->changeUser($user);
		\XF::setVisitor($user);

		$ip = $this->request->getIp();

		$this->repository(IpRepository::class)->logIp(
			$user->user_id,
			$ip,
			'user',
			$user->user_id,
			'login_admin'
		);

		if (!$user->Admin && $user->is_admin)
		{
			$admin = $this->em()->create(Admin::class);
			$admin->user_id = $user->user_id;
			$admin->last_login = \XF::$time;
			$admin->save();
		}
		else
		{
			$user->Admin->last_login = \XF::$time;
			$user->Admin->save();
		}

		$this->session()->passwordConfirm = \XF::$time;

		/** @var Session $publicSession */
		$publicSession = $this->app['session.public'];
		if (!$publicSession['userId'])
		{
			$publicSession->changeUser($user);
			$publicSession->save();
			$publicSession->applyToResponse($this->app->response());
		}

		// this is just a sanity check -- faster to run here than on every page if internal_data is remote
		if (!File::installLockExists())
		{
			File::writeInstallLock();
		}
	}

	public function actionLogout()
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$this->session()->logoutUser();

		return $this->redirect($this->buildLink('index'));
	}

	public function actionPasswordConfirm()
	{
		return $this->plugin(LoginPlugin::class)->actionPasswordConfirm();
	}

	public function actionKeepAlive()
	{
		return $this->plugin(LoginPlugin::class)->actionKeepAlive();
	}

	public function checkCsrfIfNeeded($action, ParameterBag $params)
	{
		switch (strtolower($action))
		{
			case 'keepalive':
				return;
		}

		parent::checkCsrfIfNeeded($action, $params);
	}

	public function assertAdmin()
	{
	}

	public function assertCorrectVersion($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
	}
}
