<?php

namespace XF\Tfa;

use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\PasskeyRepository;
use XF\Service\Passkey\ManagerService;

class Passkey extends AbstractProvider
{
	public function generateInitialData(User $user, array $config = []): array
	{
		return [];
	}

	public function trigger($context, User $user, array &$config, Request $request): array
	{
		$passkey = \XF::service(ManagerService::class);
		$passkey->saveStateToSession(\XF::session());

		return [];
	}

	public function render($context, User $user, array $config, array $triggerData): string
	{
		$passkey = \XF::service(ManagerService::class, \XF::session());

		$passkeyRepo = \XF::repository(PasskeyRepository::class);
		$existingCredentials = $passkeyRepo->getExistingCredentialsForUser($user);

		$params = [
			'user' => $user,
			'config' => $config,
			'context' => $context,
			'provider' => $this->getProvider(),
			'passkey' => $passkey,
			'existingCredentials' => $existingCredentials,
		];
		return \XF::app()->templater()->renderTemplate('public:two_step_passkey', $params);
	}

	public function verify($context, User $user, array &$config, Request $request): bool
	{
		if ($context !== 'login')
		{
			// passkeys are created through the account security page
			return false;
		}

		$session = \XF::session();
		$passkey = \XF::service(ManagerService::class, $session);

		if (!$passkey->validate($request, $error))
		{
			$passkey->clearStateFromSession($session);
			return false;
		}

		return true;
	}

	public function canDisable(): bool
	{
		return false;
	}

	public function canEnable()
	{
		return false;
	}

	public function canManage(): bool
	{
		return true;
	}

	public function handleManage(Controller $controller, TfaProvider $provider, User $user, array $config): ?AbstractReply
	{
		return $controller->redirect($controller->buildLink('account/security'));
	}
}
