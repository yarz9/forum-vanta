<?php

namespace XF\Tfa;

use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Repository\TfaRepository;

use function ord;

class Backup extends AbstractProvider
{
	public function generateInitialData(User $user, array $config = [])
	{
		$codes = [];
		$total = 10;
		$length = 9;
		$random = \XF::generateRandomString(4 * $total, true);

		for ($i = 0; $i < $total; $i++)
		{
			$offset = $i * 4; // 4 bytes for each set

			$code = (
				((ord($random[$offset + 0]) & 0x7f) << 24) |
				((ord($random[$offset + 1]) & 0xff) << 16) |
				((ord($random[$offset + 2]) & 0xff) << 8) |
				(ord($random[$offset + 3]) & 0xff)
			);
			$code = $code % 10 ** $length;
			$code = str_pad($code, $length, '0', STR_PAD_LEFT);

			$codes[] = $code;
		}

		return [
			'codes' => $codes,
			'used' => [],
		];
	}

	public function trigger($context, User $user, array &$config, Request $request)
	{
		return [];
	}

	public function render($context, User $user, array $config, array $triggerData)
	{
		$params = [
			'config' => $config,
			'context' => $context,
		];
		return \XF::app()->templater()->renderTemplate('public:two_step_backup', $params);
	}

	public function verify($context, User $user, array &$config, Request $request)
	{
		$code = $request->filter('code', 'str');
		$code = preg_replace('/[^0-9]/', '', $code);
		if (!$code)
		{
			return false;
		}

		$matched = null;

		foreach ($config['codes'] AS $i => $expectedCode)
		{
			if (hash_equals($expectedCode, $code))
			{
				$matched = $i;
				break;
			}
		}

		if ($matched === null)
		{
			return false;
		}

		$config['used'][] = $config['codes'][$matched];
		unset($config['codes'][$matched]);

		if (!$config['codes'])
		{
			// regenerate automatically
			$regenerated = true;
			$config = $this->generateInitialData($user);
		}
		else
		{
			$regenerated = false;
		}

		$ip = $request->getIp();

		if ($user->email)
		{
			\XF::mailer()->newMail()
				->setToUser($user)
				->setTemplate('two_step_login_backup', [
					'user' => $user,
					'ip' => $ip,
					'regenerated' => $regenerated,
				])
				->send();
		}

		return true;
	}

	public function canEnable()
	{
		return false;
	}

	public function canDisable()
	{
		return false;
	}

	public function canManage()
	{
		return true;
	}

	public function handleManage(Controller $controller, TfaProvider $provider, User $user, array $config)
	{
		$request = $controller->request();

		if ($request->isPost())
		{
			if ($request->filter('regen', 'bool'))
			{
				$newProviderData = $this->generateInitialData($user);

				/** @var TfaRepository $tfaRepo */
				$tfaRepo = \XF::repository(TfaRepository::class);
				$tfaRepo->updateUserTfaData($user, $provider, $newProviderData);

				return $controller->redirect($controller->buildLink('account/two-step/manage', $provider));
			}
			else
			{
				return null;
			}
		}

		$viewParams = [
			'provider' => $provider,
			'user' => $user,
			'providerData' => $config,
			'usedCodes' => $this->formatCodesForDisplay($config['used']),
			'availableCodes' => $this->formatCodesForDisplay($config['codes']),
		];
		return $controller->view(
			'XF:Account\TwoStepBackupManage',
			'account_two_step_backup_manage',
			$viewParams
		);
	}

	public function formatCodesForDisplay(array $codes)
	{
		foreach ($codes AS &$code)
		{
			$code = implode(' ', str_split($code, 3));
		}

		return $codes;
	}
}
