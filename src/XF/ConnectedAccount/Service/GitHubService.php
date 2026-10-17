<?php

namespace XF\ConnectedAccount\Service;

use OAuth\OAuth2\Service\GitHub;

class GitHubService extends GitHub
{
	/**
	 * Read access to a user’s profile
	 */
	public const SCOPE_READ_USER = 'read:user';

	protected function getAuthorizationMethod()
	{
		return static::AUTHORIZATION_METHOD_HEADER_BEARER;
	}

	protected function getExtraApiHeaders()
	{
		return ['Accept' => 'application/vnd.github.v3+json'];
	}
}
