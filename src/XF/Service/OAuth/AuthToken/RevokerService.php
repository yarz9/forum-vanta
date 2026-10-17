<?php

namespace XF\Service\OAuth\AuthToken;

use XF\App;
use XF\Entity\OAuthToken;
use XF\Service\AbstractService;

class RevokerService extends AbstractService
{
	/**
	 * @var OAuthToken
	 */
	protected $authToken;

	public function __construct(App $app, OAuthToken $refreshToken)
	{
		parent::__construct($app);

		$this->authToken = $refreshToken;
	}

	public function revoke(): bool
	{
		if ($this->authToken->isValid())
		{
			return false;
		}

		$this->authToken->revoked_date = \XF::$time;
		$this->authToken->save();

		$this->onRevoke();

		return true;
	}

	protected function onRevoke(): void
	{
		$refreshTokens = $this->authToken->OAuthRefreshTokens;

		foreach ($refreshTokens AS $refreshToken)
		{
			$refreshTokenRevoker = $this->service(\XF\Service\OAuth\RefreshToken\RevokerService::class, $refreshToken);
			$refreshTokenRevoker->revoke();
		}
	}
}
