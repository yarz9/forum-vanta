<?php

namespace XF\ConnectedAccount\Service;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;
use XF\Entity\ConnectedAccountProvider;

use function is_array;

class XenForoService extends AbstractService implements ProviderIdAwareInterface
{
	use ProviderIdAware;

	protected function getAuthorizationMethod(): int
	{
		return static::AUTHORIZATION_METHOD_HEADER_BEARER;
	}

	protected function parseAccessTokenResponse($responseBody): StdOAuth2Token
	{
		$data = json_decode($responseBody, true);

		if (!is_array($data))
		{
			throw new TokenResponseException('Unable to parse response');
		}

		if (isset($data['error']))
		{
			throw new TokenResponseException('Error in retrieving token: "' . $data['error_description'] . '"');
		}

		$token = new StdOAuth2Token();
		$token->setAccessToken($data['access_token']);
		$token->setLifetime($data['expires_in']);

		// todo: refresh token

		unset($data['access_token'], $data['expires_in']);

		$token->setExtraParams($data);

		return $token;
	}

	public function getAuthorizationEndpoint(): Uri
	{
		$provider = $this->getProvider();
		$endpoint = new Uri($provider->options['board_url']);
		$endpoint->setPath(rtrim($endpoint->getPath(), '/') . '/oauth2/authorize');

		return $endpoint;
	}

	public function getAccessTokenEndpoint(): Uri
	{
		$provider = $this->getProvider();
		$endpoint = new Uri($provider->options['board_url']);
		$endpoint->setPath(rtrim($endpoint->getPath(), '/') . '/api/oauth2/token');

		return $endpoint;
	}

	protected function getProvider(): ConnectedAccountProvider
	{
		return \XF::app()->em()->find(
			ConnectedAccountProvider::class,
			$this->providerId
		);
	}

	public function isValidScope($scope)
	{
		// The XenForo instance we're connecting to may have scopes that we don't know about and can't
		// use as constants in this class. They'll be validated by that XenForo instance instead.
		return true;
	}
}
