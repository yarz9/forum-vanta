<?php

namespace XF\ConnectedAccount\Service;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;

use function is_array;

class AppleService extends AbstractService
{
	public const SCOPE_NAME = 'name';
	public const SCOPE_EMAIL = 'email';

	protected function parseAccessTokenResponse($responseBody)
	{
		$data = json_decode($responseBody, true);

		if (null === $data || !is_array($data))
		{
			throw new TokenResponseException('Unable to parse response.');
		}
		else if (isset($data['error']))
		{
			throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
		}

		$token = new StdOAuth2Token();
		$token->setAccessToken($data['access_token']);
		$token->setLifetime($data['expires_in']);

		if (isset($data['refresh_token']))
		{
			$token->setRefreshToken($data['refresh_token']);
			unset($data['refresh_token']);
		}

		unset($data['access_token']);
		unset($data['expires_in']);

		$token->setExtraParams($data);

		return $token;
	}

	public function getAuthorizationEndpoint()
	{
		return new Uri('https://appleid.apple.com/auth/authorize');
	}

	public function getAuthorizationUri(array $additionalParameters = [])
	{
		return parent::getAuthorizationUri([
			'response_mode' => 'form_post',
		]);
	}

	public function getAccessTokenEndpoint()
	{
		return new Uri('https://appleid.apple.com/auth/token');
	}
}
