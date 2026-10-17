<?php

namespace XF\ControllerPlugin;

use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\UserRemember;
use XF\Repository\IpRepository;
use XF\Repository\PreRegActionRepository;
use XF\Repository\SessionActivityRepository;
use XF\Repository\TfaRepository;
use XF\Repository\UserRememberRepository;
use XF\Repository\UserTfaTrustedRepository;
use XF\Service\User\LoginService;
use XF\Service\User\TfaService;

use function in_array;

class LoginPlugin extends AbstractPlugin
{
	public function isTfaConfirmationRequired(User $user)
	{
		$trustKey = $this->getCurrentTrustKey();

		/** @var TfaRepository $tfaRepo */
		$tfaRepo = $this->repository(TfaRepository::class);
		return $tfaRepo->isUserTfaConfirmationRequired($user, $trustKey);
	}

	public function getCurrentTrustKey()
	{
		return $this->request->getCookie('tfa_trust');
	}

	public function getTfaLoginUserId()
	{
		if (!$this->session->tfaLoginUserId || \XF::visitor()->user_id)
		{
			return null;
		}

		if (!$this->session->tfaLoginDate || $this->session->tfaLoginDate < time() - 900)
		{
			return null;
		}

		return $this->session->tfaLoginUserId;
	}

	/**
	 * @return null|User
	 */
	public function getTfaLoginUser()
	{
		$userId = $this->getTfaLoginUserId();
		if (!$userId)
		{
			return null;
		}

		return $this->em->find(User::class, $userId, ['Option']);
	}

	public function setTfaSessionCheck(User $user)
	{
		$this->session->tfaLoginUserId = $user->user_id;
		$this->session->tfaLoginDate = time();
	}

	public function clearTfaSessionCheck()
	{
		unset($this->session->tfaLoginUserId);
		unset($this->session->tfaLoginDate);
	}

	public function triggerIfTfaConfirmationRequired(User $user, $callbackOrUrl)
	{
		if ($this->isTfaConfirmationRequired($user))
		{
			$this->setTfaSessionCheck($user);

			if ($callbackOrUrl instanceof \Closure)
			{
				$callbackOrUrl();
			}
			else
			{
				throw $this->exception($this->redirect($callbackOrUrl, ''));
			}
		}
	}

	/**
	 * @param string $redirect
	 * @param null|string $providerId
	 *
	 * @return LoginTfaResultPlugin
	 */
	public function runTfaCheck($redirect, $providerId = null)
	{
		if ($providerId === null)
		{
			$providerId = $this->request->filter('provider', 'str');
		}

		$user = $this->getTfaLoginUser();
		if (!$user)
		{
			$this->clearTfaSessionCheck();
			return LoginTfaResultPlugin::newSkipped($redirect);
		}

		/** @var TfaService $tfaService */
		$tfaService = $this->service(TfaService::class, $user);

		if (!$tfaService->isTfaAvailable())
		{
			$this->clearTfaSessionCheck();
			return LoginTfaResultPlugin::newSuccess($user, $redirect);
		}

		if (
			$this->request->isPost()
			&& $this->request->filter('confirm', 'bool')
			&& $tfaService->isProviderValid($providerId)
		)
		{
			if ($tfaService->hasTooManyTfaAttempts())
			{
				return LoginTfaResultPlugin::newError(
					\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts')
				);
			}

			$verified = $tfaService->verify($this->request, $providerId);
			if (!$verified)
			{
				return LoginTfaResultPlugin::newError(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
			}
			else
			{
				$this->clearTfaSessionCheck();

				if ($this->filter('trust', 'bool'))
				{
					$this->setDeviceTrusted($user->user_id);
				}

				return LoginTfaResultPlugin::newSuccess($user, $redirect);
			}
		}

		$triggered = $tfaService->trigger($this->request, $providerId);

		$this->repository(IpRepository::class)->logIp(
			$user->user_id,
			$this->request->getIp(),
			'user',
			$user->user_id,
			'login_tfa'
		);

		return LoginTfaResultPlugin::newForm([
			'user' => $user,
			'providers' => $tfaService->getProviders(),
			'providerId' => $triggered['provider']->provider_id,
			'provider' => $triggered['provider'],
			'providerData' => $triggered['providerData'],
			'triggerData' => $triggered['triggerData'],
			'redirect' => $redirect,
		]);
	}

	public function setDeviceTrusted($userId)
	{
		/** @var UserTfaTrustedRepository $tfaTrustRepo */
		$tfaTrustRepo = $this->repository(UserTfaTrustedRepository::class);
		$key = $tfaTrustRepo->createTrustedKey($userId);

		$this->app->response()->setCookie('tfa_trust', $key, 45 * 86400, null, true);

		return $key;
	}

	public function completeLogin(User $user, $remember)
	{
		if ($user->user_id !== \XF::visitor()->user_id)
		{
			$preRegContentUrl = null;

			if (!empty($this->options()->preRegAction['enabled']))
			{
				/** @var PreRegActionRepository $preRegActionRepo */
				$preRegActionRepo = $this->repository(PreRegActionRepository::class);

				$preRegActionKey = $this->session->preRegActionKey;
				if ($preRegActionKey)
				{
					$preRegActionRepo->associateActionWithUser($preRegActionKey, $user->user_id);
				}

				$preRegActionRepo->completeUserActionIfPossible($user, $preRegContent);

				if ($preRegContent instanceof LinkableInterface)
				{
					$preRegContentUrl = $preRegContent->getContentUrl();
				}
			}

			$this->session->changeUser($user);

			if ($preRegContentUrl)
			{
				$this->session->preRegContentUrl = $preRegContentUrl;
			}

			\XF::setVisitor($user);
		}

		$ip = $this->request->getIp();

		$this->repository(SessionActivityRepository::class)->clearUserActivity(0, $ip);

		$this->repository(IpRepository::class)->logIp(
			$user->user_id,
			$ip,
			'user',
			$user->user_id,
			'login'
		);

		if ($remember)
		{
			$this->createVisitorRememberKey();
		}
	}

	public function actionPasswordConfirm()
	{
		$redirect = $this->controller->getDynamicRedirectIfNot($this->buildLink('login'));
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return $this->redirect($redirect, '');
		}

		$this->assertPostOnly();

		/** @var LoginService $loginService */
		$loginService = $this->service(LoginService::class, $visitor->username, $this->request->getIp());
		if ($loginService->isLoginLimited())
		{
			return $this->error(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
		}

		$password = $this->filter('password', 'str');
		if (!$loginService->validate($password, $error))
		{
			return $this->error($error);
		}

		$this->session()->passwordConfirm = \XF::$time;
		return $this->redirect($redirect, '');
	}

	public function actionKeepAlive()
	{
		$this->controller->assertPostOnly();

		// if there's no cookie, then we need to generate a new one
		if ($this->request->getCookie('csrf'))
		{
			$this->controller->assertValidCsrfToken(null, 0); // ignore time errors and allow it to be updated in all cases
		}

		$json = [
			'csrf' => $this->app['csrf.token'],
			'time' => \XF::$time,
			'user_id' => \XF::visitor()->user_id,
		];
		$view = $this->view();
		$view->setJsonParams($json);
		return $view;
	}

	public function createVisitorRememberKey()
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return;
		}

		/** @var UserRememberRepository $rememberRepo */
		$rememberRepo = $this->repository(UserRememberRepository::class);
		$key = $rememberRepo->createRememberRecord($visitor->user_id);
		$value = $rememberRepo->getCookieValue($visitor->user_id, $key);

		$this->app->response()->setCookie('user', $value, 365 * 86400);
	}

	public function logoutVisitor()
	{
		$this->lastActivityUpdate();
		$this->deleteVisitorRememberRecord(false);
		$this->session->logoutUser();
		$this->clearCookies();
		$this->clearSiteData();
	}

	public function lastActivityUpdate()
	{
		$visitor = \XF::visitor();
		$userId = $visitor->user_id;
		if (!$userId)
		{
			return;
		}

		$activity = $visitor->Activity;
		if (!$activity)
		{
			return;
		}

		$visitor->last_activity = $activity->view_date;
		$visitor->save();

		$activity->delete();
	}

	public function deleteVisitorRememberRecord($deleteCookie = true)
	{
		$userRemember = $this->validateVisitorRememberKey();
		if ($userRemember)
		{
			$userRemember->delete();
		}

		if ($deleteCookie)
		{
			$this->app->response()->setCookie('user', false);
		}
	}

	/**
	 * @return null|UserRemember
	 */
	public function validateVisitorRememberKey()
	{
		$rememberCookie = $this->request->getCookie('user');
		if (!$rememberCookie)
		{
			return null;
		}

		/** @var UserRememberRepository $rememberRepo */
		$rememberRepo = $this->repository(UserRememberRepository::class);
		if ($rememberRepo->validateByCookieValue($rememberCookie, $remember))
		{
			return $remember;
		}
		else
		{
			return null;
		}
	}

	protected function clearCookieSkipList()
	{
		return [
			'consent',
			'notice_dismiss',
			'push_notice_dismiss',
			'session',
			'tfa_trust',
		];
	}

	public function clearCookies()
	{
		$skip = $this->clearCookieSkipList();
		$response = $this->app->response();

		foreach ($this->request->getCookies() AS $cookie => $null)
		{
			if (in_array($cookie, $skip))
			{
				continue;
			}

			$response->setCookie($cookie, false);
		}
	}

	public function clearSiteData()
	{
		// TODO: This causes performance issues on the client side in Chrome. See XF-167665.
		// $response = $this->app->response();
		// $response->header('Clear-Site-Data', '"cache"');
	}

	public function handleVisitorPasswordChange()
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return;
		}

		/** @var UserRememberRepository $rememberRepo */
		$rememberRepo = $this->repository(UserRememberRepository::class);

		$userRemember = $this->validateVisitorRememberKey();

		$rememberRepo->clearUserRememberRecords($visitor->user_id);

		if ($userRemember)
		{
			// had a remember key before which has been invalidated, so give another one
			$this->createVisitorRememberKey();
		}

		// this will reset the necessary details in the session (such as password date)
		$this->session->changeUser($visitor);
	}
}
