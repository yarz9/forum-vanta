<?php

namespace XF\Service\OAuth;

use XF\App;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthCode;
use XF\Entity\OAuthRequest;
use XF\Service\AbstractService;

class AuthManagerService extends AbstractService
{
	protected $client;

	public function __construct(App $app, OAuthClient $client)
	{
		parent::__construct($app);

		$this->client = $client;
	}

	public function createAuthCodeFromRequest(OAuthRequest $request): OAuthCode
	{
		$code = $this->em()->create(OAuthCode::class);
		$code->oauth_request_id = $request->oauth_request_id;
		$code->save();

		return $code;
	}
}
