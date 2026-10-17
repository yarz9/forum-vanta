<?php

namespace XF\Pub\Controller;

use OAuth\Common\Http\Uri\Uri;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthRequest;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\ApiRepository;
use XF\Repository\OAuthRepository;
use XF\Service\OAuth\AuthManagerService;
use XF\Service\OAuth\AuthRequestService;

use function is_string;

class OAuth2Controller extends AbstractController
{
	public function actionAuthorize()
	{
		$this->assertRegistrationRequired();

		$clientId = $this->filter('client_id', 'str');

		$client = $this->em()->find(OAuthClient::class, $clientId);
		if (!$client || !$client->active)
		{
			return $this->error(\XF::phrase('requested_page_not_found'), 404);
		}

		$visitor = \XF::visitor();
		$authManager = $this->service(AuthManagerService::class, $client);

		if ($this->isPost())
		{
			$authRequestId = $this->filter('oauth_request_id', 'str');

			$authRequest = $this->em()->findOne(OAuthRequest::class, [
				'oauth_request_id' => $authRequestId,
				'client_id' => $client->client_id,
				'user_id' => $visitor->user_id,
			]);

			if (!$authRequest)
			{
				return $this->error(\XF::phrase('requested_page_not_found'), 404);
			}

			$authCode = $authManager->createAuthCodeFromRequest($authRequest);

			return $this->redirect($this->buildRedirectUri($authRequest->redirect_uri, [
				'code' => $authCode->code,
			], $authRequest->state));
		}

		$input = $this->getOAuthRequestInput($client);

		$authRequestService = $this->service(AuthRequestService::class, $client);
		$authRequestService->setResponseType($input['response_type']);
		$authRequestService->setRedirectUri($input['redirect_uri']);

		if ($input['state'])
		{
			$authRequestService->setState($input['state']);
		}

		$requestedScopes = [];

		if ($input['scope'])
		{
			$requestedScopes = explode(' ', $input['scope']);
			$requestedScopes = array_flip($requestedScopes);

			$allowedScopes = $this->repository(ApiRepository::class)
				->getApiScopesByIds(array_flip($client->allowed_scopes));

			if (!$allowedScopes)
			{
				return $this->error(\XF::phrase('no_scopes_allowed_for_this_client'));
			}

			foreach ($requestedScopes AS $requestedScope => $key)
			{
				$scope = $allowedScopes[$requestedScope] ?? null;

				if (!$scope)
				{
					unset($requestedScopes[$requestedScope]);
				}
			}
		}

		if (!$requestedScopes)
		{
			return $this->error(\XF::phrase('no_valid_scopes_were_requested'));
		}

		$authRequestService->setScopes(array_keys($requestedScopes));

		if ($input['code_challenge'] && $input['code_challenge_method'])
		{
			$authRequestService->setCodeChallenge($input['code_challenge'], $input['code_challenge_method']);
		}

		$authRequest = $authRequestService->save();

		$scopes = $this->repository(ApiRepository::class)->getApiScopesByIds($authRequest->scopes);

		$linkParams = $input;
		$linkParams['client_id'] = $client->client_id;
		$linkParams['oauth_request_id'] = $authRequest->oauth_request_id;

		$viewParams = [
			'client' => $client,
			'authRequest' => $authRequest,
			'scopes' => $scopes,
			'linkParams' => $linkParams,
		];

		return $this->view('XF:OAuth\Authorize', 'oauth_authorize', $viewParams);
	}

	/**
	 * @throws Exception
	 */
	public function actionDeny(ParameterBag $params)
	{
		$this->assertRegistrationRequired();

		$clientId = $this->filter('client_id', 'str');

		$client = $this->em()->find(OAuthClient::class, $clientId);
		if (!$client || !$client->active)
		{
			return $this->error(\XF::phrase('requested_page_not_found'), 400);
		}

		$input = $this->getOAuthRequestInput($client);

		return $this->redirect($this->buildRedirectUri($input['redirect_uri'], [
			'error' => 'access_denied',
		], $input['state']));
	}

	/**
	 * @throws Exception
	 */
	protected function getOAuthRequestInput(OAuthClient $client): array
	{
		$input = $this->filter([
			'redirect_uri' => 'str',
			'response_type' => 'str',
			'state' => '?str',
			'scope' => 'str',
			'code_challenge' => '?str',
			'code_challenge_method' => '?str',
		]);

		if (empty($input['redirect_uri']))
		{
			throw $this->exception($this->error(\XF::phrase('valid_redirect_uri_is_required'), 400));
		}

		$inputRedirectUri = new Uri($input['redirect_uri']);
		$redirectUris = $client->redirect_uris;
		$validUri = false;

		foreach ($redirectUris AS $redirectUri)
		{
			$clientRedirectUri = new Uri($redirectUri);

			if ($clientRedirectUri->getAbsoluteUri() === $inputRedirectUri->getAbsoluteUri())
			{
				$validUri = true;
				break;
			}
		}

		if (!$validUri)
		{
			$errorRedirectUri = $this->buildRedirectUri($inputRedirectUri, [
				'error' => 'invalid_request',
				'error_description' => 'Provided redirect_uri does not match redirect_uri registered',
			], $input['state']);

			throw $this->exception($this->redirect($errorRedirectUri));
		}

		if ($input['response_type'] !== OAuthRepository::RESPONSE_TYPE_CODE)
		{
			$errorRedirectUri = $this->buildRedirectUri($inputRedirectUri, [
				'error' => 'unsupported_response_type',
				'error_description' => 'Only the code response type is supported',
			], $input['state']);

			throw $this->exception($this->redirect($errorRedirectUri));
		}

		if ($client->client_type === OAuthRepository::CLIENT_TYPE_PUBLIC
			&& (!$input['code_challenge'] || !$input['code_challenge_method'])
		)
		{
			$errorRedirectUri = $this->buildRedirectUri($inputRedirectUri, [
				'error' => 'invalid_request',
				'error_description' => 'code_challenge and code_challenge_method are required for public clients',
			], $input['state']);

			throw $this->exception($this->redirect($errorRedirectUri));
		}

		if ($input['code_challenge']
			&& $input['code_challenge_method'] !== OAuthRepository::CODE_CHALLENGE_METHOD_S256
		)
		{
			$errorRedirectUri = $this->buildRedirectUri($inputRedirectUri, [
				'error' => 'invalid_request',
				'error_description' => 'code_challenge_method must be S256',
			], $input['state']);

			throw $this->exception($this->redirect($errorRedirectUri));
		}

		return $input;
	}

	protected function buildRedirectUri($redirectUri, array $params, ?string $state = null): string
	{
		if (!is_string($redirectUri) && !$redirectUri instanceof Uri)
		{
			throw new \InvalidArgumentException('Redirect URI must be a string or instance of Uri');
		}

		if (!$redirectUri instanceof Uri)
		{
			$redirectUri = new Uri($redirectUri);
		}

		foreach ($params AS $key => $value)
		{
			$redirectUri->addToQuery($key, $value);
		}

		if ($state)
		{
			$redirectUri->addToQuery('state', $state);
		}

		return $redirectUri->getAbsoluteUri();
	}
}
