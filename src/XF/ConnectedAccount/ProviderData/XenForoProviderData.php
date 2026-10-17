<?php

namespace XF\ConnectedAccount\ProviderData;

use XF\Entity\ConnectedAccountProvider;

class XenForoProviderData extends AbstractProviderData
{
	public function getDefaultEndpoint(): string
	{
		$provider = \XF::app()->em()->find(
			ConnectedAccountProvider::class,
			$this->providerId
		);

		return $provider->options['board_url'] . '/api/me';
	}

	public function getProviderKey()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['user_id'] ?? null;
	}

	public function getUsername()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['username'] ?? null;
	}

	public function getEmail()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['email'] ?? null;
	}

	public function getProfileLink()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['view_url'] ?? null;
	}

	public function getLocation()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['location'] ?? null;
	}

	public function getAvatarUrl()
	{
		$data = $this->requestFromEndpoint();
		return $data['me']['avatar_urls']['o'] ?? null;
	}
}
