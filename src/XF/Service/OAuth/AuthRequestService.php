<?php

namespace XF\Service\OAuth;

use XF\App;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthRequest;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class AuthRequestService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var OAuthClient
	 */
	protected $client;

	protected $authRequest;

	public function __construct(App $app, OAuthClient $client)
	{
		parent::__construct($app);

		$this->client = $client;

		$this->authRequest = $this->em()->create(OAuthRequest::class);
		$this->authRequest->client_id = $client->client_id;
		$this->authRequest->user_id = \XF::visitor()->user_id;
	}

	public function getAuthRequest(): OAuthRequest
	{
		return $this->authRequest;
	}

	public function setResponseType(string $responseType): void
	{
		$this->authRequest->response_type = $responseType;
	}

	public function setRedirectUri(string $redirectUri): void
	{
		$this->authRequest->redirect_uri = $redirectUri;
	}

	public function setState(string $state): void
	{
		$this->authRequest->state = $state;
	}

	public function setScopes(array $scopes): void
	{
		$this->authRequest->scopes = $scopes;
	}

	public function setCodeChallenge(string $codeChallenge, string $codeChallengeMethod): void
	{
		$this->authRequest->code_challenge = $codeChallenge;
		$this->authRequest->code_challenge_method = $codeChallengeMethod;
	}

	protected function finalSetup(): void
	{
		$this->authRequest->request_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->authRequest->preSave();
		return $this->authRequest->getErrors();
	}

	protected function _save()
	{
		$this->authRequest->save();
		return $this->authRequest;
	}
}
