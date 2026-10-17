<?php

namespace XF\ConnectedAccount\Provider;

use OAuth\OAuth2\Service\Facebook as FacebookService;
use XF\ConnectedAccount\Http\HttpResponseException;
use XF\ConnectedAccount\ProviderData\FacebookProviderData;
use XF\Entity\ConnectedAccountProvider;

use function is_array;

class FacebookProvider extends AbstractProvider
{
	public function getOAuthServiceName()
	{
		return FacebookService::class;
	}

	public function getProviderDataClass(): string
	{
		return FacebookProviderData::class;
	}

	public function getDefaultOptions()
	{
		return [
			'app_id' => '',
			'app_secret' => '',
		];
	}

	public function getOAuthConfig(ConnectedAccountProvider $provider, $redirectUri = null)
	{
		return [
			'key' => $provider->options['app_id'],
			'secret' => $provider->options['app_secret'],
			'scopes' => ['email'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
		];
	}

	public function parseProviderError(HttpResponseException $e, &$error = null)
	{
		$response = json_decode($e->getResponseContent(), true);
		if (is_array($response) && isset($response['error']['message']))
		{
			$e->setMessage($response['error']['message']);
		}
		parent::parseProviderError($e, $error);
	}

	public function getIconClass(): ?string
	{
		return 'fab fa-facebook-f';
	}
}
