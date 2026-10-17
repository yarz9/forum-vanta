<?php

namespace XF\Service\OAuth\RefreshToken;

use XF\App;
use XF\Entity\OAuthRefreshToken;
use XF\Service\AbstractService;

class RevokerService extends AbstractService
{
	/**
	 * @var OAuthRefreshToken
	 */
	protected $refreshToken;

	public function __construct(App $app, OAuthRefreshToken $refreshToken)
	{
		parent::__construct($app);

		$this->refreshToken = $refreshToken;
	}

	public function revoke(): bool
	{
		if ($this->refreshToken->isValid())
		{
			return false;
		}

		$this->refreshToken->revoked_date = \XF::$time;
		$this->refreshToken->save();

		$this->onRevoke();

		return true;
	}

	protected function onRevoke(): void
	{
		//
	}
}
