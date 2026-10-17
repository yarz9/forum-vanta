<?php

namespace XF\ConnectedAccount\Service;

use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth1\Service\Twitter;
use OAuth\OAuth1\Signature\SignatureInterface;

class XService extends Twitter implements ProviderIdAwareInterface
{
	use ProviderIdAware;

	public const ENDPOINT_AUTHENTICATE = 'https://api.x.com/oauth/authenticate';
	public const ENDPOINT_AUTHORIZE = 'https://api.x.com/oauth/authorize';

	public function __construct(
		CredentialsInterface $credentials,
		ClientInterface $httpClient,
		TokenStorageInterface $storage,
		SignatureInterface $signature,
		?UriInterface $baseApiUri = null
	)
	{
		parent::__construct($credentials, $httpClient, $storage, $signature, $baseApiUri);

		if (null === $baseApiUri)
		{
			$this->baseApiUri = new Uri('https://api.x.com/');
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRequestTokenEndpoint()
	{
		return new Uri('https://api.x.com/oauth/request_token');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAccessTokenEndpoint()
	{
		return new Uri('https://api.x.com/oauth/access_token');
	}
}
