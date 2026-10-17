<?php

namespace XF\ConnectedAccount\Provider;

use OAuth\OAuth2\Service\Yahoo as YahooService;
use XF\ConnectedAccount\Http\HttpResponseException;
use XF\ConnectedAccount\ProviderData\YahooProviderData;
use XF\Entity\ConnectedAccountProvider;

class YahooProvider extends AbstractProvider
{
	public function getOAuthServiceName()
	{
		return YahooService::class;
	}

	public function getProviderDataClass(): string
	{
		return YahooProviderData::class;
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
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
			'scopes' => [],
		];
	}

	public function parseProviderError(HttpResponseException $e, &$error = null)
	{
		$errorDetails = json_decode($e->getResponseContent(), true);
		if (isset($errorDetails['error_description']))
		{
			$e->setMessage($errorDetails['error_description']);
		}
		parent::parseProviderError($e, $error);
	}

	public function getIconClass(): ?string
	{
		return 'fab fa-yahoo';
	}
}
