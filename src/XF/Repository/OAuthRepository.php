<?php

namespace XF\Repository;

use XF\Entity\ApiScope;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthToken;
use XF\Entity\User;
use XF\Finder\OAuthClientFinder;
use XF\Finder\OAuthTokenFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class OAuthRepository extends Repository
{
	public const CLIENT_TYPE_PUBLIC = 'public';
	public const CLIENT_TYPE_CONFIDENTIAL = 'confidential';

	public const RESPONSE_TYPE_CODE = 'code';

	public const CODE_CHALLENGE_METHOD_S256 = 'S256';

	public function findClientsForList(): Finder
	{
		return $this->finder(OAuthClientFinder::class)
			->setDefaultOrder('creation_date', 'desc');
	}

	public function findActiveTokensForUser(User $user): Finder
	{
		return $this->finder(OAuthTokenFinder::class)
			->with('OAuthClient')
			->where('user_id', $user->user_id)
			->order('issue_date', 'desc');
	}

	public function getConnectedClientsForUser(?User $user = null): AbstractCollection
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		$uniqueClientIds = $this->db()->fetchAllColumn("
			SELECT DISTINCT client_id
			FROM xf_oauth_token
			WHERE user_id = ?
			AND revoked_date = 0
			ORDER BY issue_date DESC
		", $user->user_id);

		return $this->finder(OAuthClientFinder::class)
			->where('client_id', $uniqueClientIds)
			->where('active', 1)
			->order('client_id', 'desc')
			->fetch();
	}

	public function getScopesForTokens(OAuthClient $client)
	{
		$scopesFromTokens = $this->finder(OAuthToken::class)
			->where('client_id', $client->client_id)
			->where('user_id', \XF::visitor()->user_id)
			->fetch()
			->pluckNamed('scopes', 'token_id');

		$scopes = array_merge(...$scopesFromTokens);
		$scopes = array_keys($scopes);

		$apiScopes = $this->finder(ApiScope::class)
			->whereIds($scopes)
			->fetch();

		return $apiScopes;
	}

	public function revokeClientForUser(OAuthClient $client, ?User $user = null): void
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		$this->db()->update('xf_oauth_token', ['revoked_date' => \XF::$time], 'client_id = ? AND user_id = ?', [$client->client_id, $user->user_id]);
	}

	public function getClientCount(): int
	{
		$clients = $this->finder(OAuthClientFinder::class)->fetch();

		$clients = $clients->filter(function (OAuthClient $client)
		{
			return $client->isUsable();
		});

		return $clients->count();
	}

	public function rebuildClientCount(): int
	{
		$cache = $this->getClientCount();
		\XF::registry()->set('oAuthClientCount', $cache);
		return $cache;
	}

	public function pruneExpiredCodes(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 14;
		}

		return $this->db()->delete('xf_oauth_code', 'expiry_date < ?', $cutOff);
	}

	public function pruneAuthRequests(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 30;
		}

		return $this->db()->delete('xf_oauth_request', 'request_date < ?', $cutOff);
	}
}
