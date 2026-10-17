<?php

namespace XF\ConnectedAccount\Provider;

use XF\ConnectedAccount\Http\HttpResponseException;
use XF\ConnectedAccount\ProviderData\LinkedinProviderData;
use XF\ConnectedAccount\Service\LinkedinService;
use XF\Entity\ConnectedAccountProvider;

class LinkedinProvider extends AbstractProvider
{
	public function getOAuthServiceName()
	{
		return LinkedinService::class;
	}

	public function getProviderDataClass()
	{
		return LinkedinProviderData::class;
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
			'scopes' => ['openid', 'profile', 'email'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
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
		return 'fab fa-linkedin-in';
	}
}
