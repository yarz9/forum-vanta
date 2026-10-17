<?php

namespace XF\ConnectedAccount\ProviderData;

use XF\ConnectedAccount\Storage\StorageState;

class AppleProviderData extends AbstractProviderData
{
	protected $claims = [];

	public function __construct($providerId, StorageState $storageState)
	{
		parent::__construct($providerId, $storageState);

		$token = $storageState->getProviderToken();

		$claims = explode('.', $token->getExtraParams()['id_token'])[1];
		$claims = json_decode(base64_decode($claims), true);

		$this->claims = $claims;
	}

	public function getDefaultEndpoint()
	{
		return 'https://appleid.apple.com/auth/token';
	}

	public function getProviderKey()
	{
		return $this->claims['sub'];
	}

	public function getEmail()
	{
		return $this->claims['email'];
	}
}
