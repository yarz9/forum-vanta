<?php

namespace XF\Api\Controller;

use OAuth\Common\Http\Uri\Uri;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthCode;
use XF\Entity\OAuthRefreshToken;
use XF\Entity\OAuthRequest;
use XF\Entity\OAuthToken;
use XF\Repository\OAuthRepository;
use XF\Service\OAuth\AuthToken\CreatorService;
use XF\Service\OAuth\AuthToken\RevokerService;

class OAuth2Controller extends AbstractController
{
	public function actionPostToken()
	{
		$this->assertRequiredApiInput(['client_id', 'grant_type', 'redirect_uri']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => '?str',
			'grant_type' => '?str',
			'code' => '?str',
			'refresh_token' => '?str',
			'code_verifier' => '?str',
			'redirect_uri' => 'str',
		]);

		/** @var OAuthClient $client */
		$client = $this->finder(OAuthClient::class)
			->where('client_id', $input['client_id'])
			->where('active', 1)
			->fetchOne();
		if (!$client)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		if ($client->client_type === OAuthRepository::CLIENT_TYPE_CONFIDENTIAL)
		{
			$this->assertRequiredApiInput(['client_secret']);
		}
		else if ($client->client_type === OAuthRepository::CLIENT_TYPE_PUBLIC)
		{
			$this->assertRequiredApiInput(['code_verifier']);
		}

		if ($input['client_secret'] && $input['client_secret'] !== $client->client_secret)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		switch ($input['grant_type'])
		{
			case 'authorization_code':
				return $this->grantAuthorizationCode($client, $input);
			case 'refresh_token':
				return $this->grantRefreshToken($client, $input);
			default:
				return $this->apiError(\XF::phrase('provided_grant_type_is_not_supported'), 'unsupported_grant_type');
		}
	}

	protected function grantAuthorizationCode(OAuthClient $client, $input)
	{
		$this->assertRequiredApiInput(['code']);

		$redirectUris = $client->redirect_uris;

		$validUri = false;
		foreach ($redirectUris AS $redirectUri)
		{
			$clientRedirectUri = new Uri($redirectUri);
			$inputRedirectUri = new Uri($input['redirect_uri']);

			if ($clientRedirectUri->getAbsoluteUri() === $inputRedirectUri->getAbsoluteUri())
			{
				$validUri = true;
				break;
			}
		}

		if (!$validUri)
		{
			return $this->apiError(\XF::phrase('provided_redirection_uri_does_not_match_redirection_uri_registered'), 'invalid_grant');
		}

		/** @var OAuthCode $authCode */
		$authCode = $this->finder(OAuthCode::class)
			->where('code', $input['code'])
			->fetchOne();

		if (!$authCode || !$authCode->isValid())
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		/** @var OAuthRequest $authRequest */
		$authRequest = $authCode->OAuthRequest;

		/** @var OAuthClient $authCodeClient */
		$authCodeClient = $authRequest->OAuthClient;

		if ($authCodeClient->client_id !== $client->client_id)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_do_not_match_client_credentials_used'), 'invalid_grant');
		}

		if ($input['code_verifier'])
		{
			$codeVerifier = base64_encode(hash('sha256', $input['code_verifier'], true));
			$codeVerifier = strtr(rtrim($codeVerifier, '='), '+/', '-_');

			if ($codeVerifier !== $authRequest->code_challenge)
			{
				return $this->apiError(\XF::phrase('provided_code_verifier_does_not_match_code_challenge'), 'invalid_grant');
			}
		}

		$authTokenCreator = $this->service(CreatorService::class, $client);
		$authTokenCreator->setFromCode($authCode);

		$authToken = $authTokenCreator->save();
		$refreshToken = $authTokenCreator->getRefreshToken();

		return $this->apiResult([
			'access_token' => $authToken->token,
			'refresh_token' => $refreshToken->refresh_token,
			'token_type' => 'bearer',
			'expires_in' => OAuthToken::TOKEN_LIFETIME_SECONDS,
			'issue_date' => $authToken->issue_date,
		]);
	}

	protected function grantRefreshToken(OAuthClient $client, $input)
	{
		$this->assertRequiredApiInput(['refresh_token']);

		/** @var OAuthRefreshToken $refreshToken */
		$refreshToken = $this->finder(OAuthRefreshToken::class)
			->where('refresh_token', $input['refresh_token'])
			->fetchOne();

		if (!$refreshToken || !$refreshToken->isValid())
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$authTokenCreator = $this->service(CreatorService::class, $client);
		$authTokenCreator->setFromRefreshToken($refreshToken);

		$newAuthToken = $authTokenCreator->save();
		$newRefreshToken = $authTokenCreator->getRefreshToken();

		$oldAuthToken = $refreshToken->OAuthToken;
		$tokenRevoker = $this->service(RevokerService::class, $oldAuthToken);
		$tokenRevoker->revoke();

		return $this->apiResult([
			'access_token' => $newAuthToken->token,
			'refresh_token' => $newRefreshToken->refresh_token,
			'token_type' => 'bearer',
			'expires_in' => OAuthToken::TOKEN_LIFETIME_SECONDS,
			'issue_date' => $newAuthToken->issue_date,
		]);
	}

	public function actionGetToken(): ApiResult
	{
		$this->assertRequiredApiInput(['token']);

		/** @var OAuthToken $token */
		$token = $this->finder(OAuthToken::class)
			->where('token', $this->filter('token', 'str'))
			->fetchOne();

		return $this->apiResult([
			'user_id' => $token->user_id,
			'scope' => $token->scopes,
			'expires_in' => OAuthToken::TOKEN_LIFETIME_SECONDS,
			'issue_date' => $token->issue_date,
		]);
	}

	/**
	 * @api-desc Revokes an access token or refresh token.
	 *
	 * @api-in <req> string $client_id
	 * @api-in <req> string $client_secret
	 * @api-in <req> string $token
	 * @api-in string $token_type_hint Defaults to 'access_token' but can be 'refresh_token' to revoke a refresh token.
	 */
	public function actionPostRevoke()
	{
		$this->assertRequiredApiInput(['client_id', 'client_secret', 'token']);

		$input = $this->filter([
			'client_id' => 'str',
			'client_secret' => 'str',
			'token' => 'str',
			'token_type_hint' => '?str',
		]);

		/** @var OAuthClient $client */
		$client = $this->finder(OAuthClient::class)
			->where('client_id', $input['client_id'])
			->where('client_secret', $input['client_secret'])
			->fetchOne();
		if (!$client)
		{
			return $this->apiError(\XF::phrase('provided_client_credentials_invalid'), 'invalid_client');
		}

		switch ($input['token_type_hint'])
		{
			case 'refresh_token':
				return $this->revokeRefreshToken($client, $input);
			case 'access_token':
			default:
				return $this->revokeAuthToken($client, $input);
		}
	}

	protected function revokeRefreshToken(OAuthClient $client, array $input)
	{
		/** @var OAuthRefreshToken $refreshToken */
		$refreshToken = $this->finder(OAuthRefreshToken::class)
			->where('refresh_token', $input['token'])
			->fetchOne();

		if (!$refreshToken)
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$tokenRevoker = $this->service(\XF\Service\OAuth\RefreshToken\RevokerService::class, $refreshToken);
		$tokenRevoker->revoke();

		return $this->apiSuccess();
	}

	protected function revokeAuthToken(OAuthClient $client, array $input)
	{
		/** @var OAuthToken $authToken */
		$authToken = $this->finder(OAuthToken::class)
			->where('token', $input['token'])
			->fetchOne();

		if (!$authToken)
		{
			return $this->apiError(
				\XF::phrase('provided_authorization_code_or_refresh_token_is_invalid_expired'),
				'invalid_grant'
			);
		}

		$tokenRevoker = $this->service(RevokerService::class, $authToken);
		$tokenRevoker->revoke();

		return $this->apiSuccess();
	}

	public function allowUnauthenticatedRequest($action): bool
	{
		return true;
	}
}
