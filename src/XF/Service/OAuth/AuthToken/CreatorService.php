<?php

namespace XF\Service\OAuth\AuthToken;

use XF\App;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthCode;
use XF\Entity\OAuthRefreshToken;
use XF\Entity\OAuthToken;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var OAuthClient
	 */
	protected $client;

	/**
	 * @var OAuthToken
	 */
	protected $authToken;

	/**
	 * @var OAuthRefreshToken
	 */
	protected $refreshToken;

	public function __construct(App $app, OAuthClient $client)
	{
		parent::__construct($app);
		$this->client = $client;
		$this->setupDefaults();
	}

	public function getAuthToken(): OAuthToken
	{
		return $this->authToken;
	}

	public function getRefreshToken(): OAuthRefreshToken
	{
		return $this->refreshToken;
	}

	protected function setupDefaults(): void
	{
		$this->authToken = $this->client->getNewAuthToken();
		$this->refreshToken = $this->authToken->getNewRefreshToken();

		$this->refreshToken->client_id = $this->client->client_id;

		$this->authToken->addCascadedSave($this->refreshToken);
		$this->refreshToken->hydrateRelation('OAuthToken', $this->authToken);
	}

	public function setFromCode(OAuthCode $code): void
	{
		$authRequest = $code->OAuthRequest;

		$this->authToken->user_id = $authRequest->user_id;
		$this->authToken->scopes = $authRequest->scopes;
	}

	public function setFromRefreshToken(OAuthRefreshToken $refreshToken): void
	{
		$authToken = $refreshToken->OAuthToken;

		$this->authToken->user_id = $authToken->user_id;
		$this->authToken->scopes = $authToken->scopes;
	}

	protected function finalSetup(): void
	{
		//
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->authToken->preSave();
		return $this->authToken->getErrors();
	}

	protected function _save(): OAuthToken
	{
		$authToken = $this->authToken;

		$authToken->save();

		return $authToken;
	}
}
