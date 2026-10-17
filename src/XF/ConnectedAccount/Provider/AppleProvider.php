<?php

namespace XF\ConnectedAccount\Provider;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use XF\ConnectedAccount\ProviderData\AppleProviderData;
use XF\ConnectedAccount\Service\AppleService;
use XF\Entity\ConnectedAccountProvider;
use XF\Util\File;

class AppleProvider extends AbstractProvider
{
	public function getOAuthServiceName(): string
	{
		return AppleService::class;
	}

	public function getProviderDataClass(): string
	{
		return AppleProviderData::class;
	}

	public function getDefaultOptions(): array
	{
		return [
			'team_id' => '',
			'services_id' => '',
			'key_id' => '',
		];
	}

	public function getOAuthConfig(ConnectedAccountProvider $provider, $redirectUri = null): array
	{
		$teamId = $provider->options['team_id'];
		$servicesId = $provider->options['services_id'];
		$keyId = $provider->options['key_id'];

		try
		{
			$path = $this->getAbstractedKeyPath($provider->options['key_file']);
			$keyFile = \XF::fs()->read($path);
		}
		catch (\Exception $e)
		{
			\XF::logError("Sign in with Apple: Key not found at $path");
			throw \XF::phrasedException('unexpected_error_occurred');
		}

		$algorithmManager = new AlgorithmManager([new ES256()]);
		$jwsBuilder = new JWSBuilder($algorithmManager);

		$jws = $jwsBuilder->create()->withPayload(json_encode([
			'iat' => \XF::$time,
			'exp' => \XF::$time + 86400 * 180,
			'aud' => 'https://appleid.apple.com',
			'iss' => $teamId,
			'sub' => $servicesId,
		]))
		->addSignature(JWKFactory::createFromKey($keyFile), [
			'alg' => 'ES256',
			'kid' => $keyId,
		])->build();

		$serializer = new CompactSerializer();
		$token = $serializer->serialize($jws, 0);

		return [
			'key' => $servicesId,
			'secret' => $token,
			'scopes' => ['email'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
		];
	}

	public function verifyConfig(array &$options, &$error = null): bool
	{
		$verified = parent::verifyConfig($options, $error);

		if ($verified)
		{
			/** @var ConnectedAccountProvider $provider */
			$provider = \XF::app()->find(ConnectedAccountProvider::class, 'apple');
			$existingKey = $provider->options['key_file'] ?? null;

			$keyFile = \XF::app()->request()->getFile('options');

			if (!$keyFile && !$existingKey)
			{
				$error = \XF::phrase('please_complete_required_fields');
				return false;
			}

			if ($existingKey && !$keyFile)
			{
				$options['key_file'] = $existingKey;
				return $verified;
			}

			if ($existingKey)
			{
				File::deleteFromAbstractedPath($this->getAbstractedKeyPath($existingKey));
			}

			$options['key_file'] = 'apple-' . \XF::generateRandomString(10) . '.key';

			File::copyFileToAbstractedPath(
				$keyFile->getTempFile(),
				$this->getAbstractedKeyPath($options['key_file'])
			);
		}

		return $verified;
	}

	protected function getAbstractedKeyPath($fileName = null): string
	{
		return 'internal-data://keys/' . ($fileName ?? '');
	}
}
