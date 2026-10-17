<?php

namespace XF\Repository;

use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Entity\ConnectedAccountProvider;
use XF\Entity\User;
use XF\Entity\UserConnectedAccount;
use XF\Finder\ConnectedAccountProviderFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

use function strval;

class ConnectedAccountRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findProvidersForList()
	{
		$finder = $this->finder(ConnectedAccountProviderFinder::class)
			->order('display_order');

		return $finder;
	}

	public function getConnectedAccountProviderTitlePairs()
	{
		$providers = $this->findProvidersForList();
		return $providers->fetch()->pluckNamed('title', 'provider_id');
	}

	public function getUsableProviders($forRegistration = false)
	{
		$providers = $this->findProvidersForList()->fetch();
		$providers = $providers->filter(function (ConnectedAccountProvider $provider) use ($forRegistration)
		{
			if (!$provider->isUsable())
			{
				return false;
			}

			if ($forRegistration && !$provider->isValidForRegistration())
			{
				return false;
			}

			return true;
		});
		return $providers;
	}

	public function getConnectedAccountProviderCount()
	{
		$providers = $this->finder(ConnectedAccountProviderFinder::class)
			->fetch();

		$providers = $providers->filter(function (ConnectedAccountProvider $provider)
		{
			return $provider->isUsable();
		});

		return $providers->count();
	}

	public function rebuildProviderCount()
	{
		$cache = $this->getConnectedAccountProviderCount();
		\XF::registry()->set('connectedAccountCount', $cache);
		return $cache;
	}

	public function rebuildUserConnectedAccountCache(User $user)
	{
		$cache = [];
		$connectedAccounts = $user->ConnectedAccounts;

		foreach ($connectedAccounts AS $providerId => $provider)
		{
			$cache[$providerId] = $provider->provider_key;
		}

		$user->Profile->connected_accounts = $cache;
		$user->Profile->save();
	}

	public function getUserConnectedAccountFromProviderData(AbstractProviderData $providerData)
	{
		return $this->em->findOne(UserConnectedAccount::class, [
			'provider_key' => strval($providerData->provider_key),
			'provider' => $providerData->getProviderId(),
		], ['User']);
	}

	public function associateConnectedAccountWithUser(User $user, AbstractProviderData $providerData)
	{
		$providerId = $providerData->getProviderId();
		$providerKey = strval($providerData->provider_key);

		// The provider+key combination is unique to a single user, so if we're trying to associate this
		// account with a user, we need to remove any other association first.
		$connectedAccount = $this->em->findOne(UserConnectedAccount::class, [
			'provider' => $providerId,
			'provider_key' => $providerKey,
		]);
		if ($connectedAccount && $connectedAccount->user_id != $user->user_id)
		{
			$connectedAccount->delete();
			$connectedAccount = null;
		}

		if (!$connectedAccount)
		{
			$connectedAccount = $this->em->findOne(UserConnectedAccount::class, [
				'user_id' => $user->user_id,
				'provider' => $providerId,
			]);
		}

		if (!$connectedAccount)
		{
			$connectedAccount = $this->em->create(UserConnectedAccount::class);
			$connectedAccount->user_id = $user->user_id;
			$connectedAccount->provider = $providerId;
		}

		$connectedAccount->provider_key = $providerKey;
		$connectedAccount->extra_data = $providerData->extra_data;
		$connectedAccount->save();

		return $connectedAccount;
	}
}
