<?php

namespace XF\ConnectedAccount\Provider;

use XF\ConnectedAccount\ProviderData\GitHubProviderData;
use XF\ConnectedAccount\Service\GitHubService;
use XF\Entity\ConnectedAccountProvider;

class GitHubProvider extends AbstractProvider
{
	public function getOAuthServiceName()
	{
		return GitHubService::class;
	}

	public function getProviderDataClass()
	{
		return GitHubProviderData::class;
	}

	public function getDefaultOptions()
	{
		return [
			'client_id' => '',
			'client_secret' => '',
		];
	}

	public function getOAuthConfig(ConnectedAccountProvider $provider, $redirectUri = null)
	{
		return [
			'key' => $provider->options['client_id'],
			'secret' => $provider->options['client_secret'],
			'scopes' => ['read:user', 'user:email'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
		];
	}

	public function getIconClass(): ?string
	{
		return 'fab fa-github';
	}
}
