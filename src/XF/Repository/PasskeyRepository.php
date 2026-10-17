<?php

namespace XF\Repository;

use XF\Entity\Passkey;
use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Entity\UserTfa;
use XF\Finder\PasskeyFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class PasskeyRepository extends Repository
{
	public function getExistingCredentialsForUser($user = null): array
	{
		if ($user === null)
		{
			$user = \XF::visitor();
		}

		$passkeys = $this->finder(PasskeyFinder::class)
			->where('user_id', $user->user_id)
			->fetch();

		$credentials = [];

		foreach ($passkeys AS $passkey)
		{
			$credentials[] = $passkey->credential_id;
		}

		return $credentials;
	}

	public function findPasskeysForUser(User $user): Finder
	{
		return $this->finder(Passkey::class)
			->where('user_id', $user->user_id)
			->order('last_use_date', 'ASC');
	}

	public function updateUserPasskeyProvider(User $user): bool
	{
		$passkeys = $this->findPasskeysForUser($user)->fetch();

		/** @var TfaProvider $provider */
		$provider = \XF::em()->find(TfaProvider::class, 'passkey');

		if ($passkeys->count() === 0)
		{
			/** @var UserTfa|null $userTfa */
			$userTfa = $provider->UserEntries[$user->user_id];
			if ($userTfa)
			{
				$userTfa->delete();
			}
		}
		else
		{
			/** @var UserTfa $userTfa */
			$userTfa = $provider->UserEntries[$user->user_id];
			if (!$userTfa)
			{
				$tfaRepo = $this->repository(TfaRepository::class);
				$tfaRepo->enableUserTfaProvider($user, $provider, []);
			}
		}

		return true;
	}
}
