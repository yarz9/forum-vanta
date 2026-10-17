<?php

namespace XF\ConnectedAccount\Provider;

use OAuth\OAuth2\Service\Microsoft as MicrosoftService;
use XF\ConnectedAccount\ProviderData\MicrosoftProviderData;
use XF\Entity\ConnectedAccountProvider;

class MicrosoftProvider extends AbstractProvider
{
	public function getOAuthServiceName()
	{
		return MicrosoftService::class;
	}

	public function getProviderDataClass(): string
	{
		return MicrosoftProviderData::class;
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
			'scopes' => ['basic', 'signin', 'birthday', 'emails'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
		];
	}

	public function getIconClass(): ?string
	{
		return 'fab fa-windows';
	}
}
