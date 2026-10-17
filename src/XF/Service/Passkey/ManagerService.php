<?php

namespace XF\Service\Passkey;

use lbuchs\WebAuthn\WebAuthn;
use XF\App;
use XF\Entity\Passkey;
use XF\Entity\User;
use XF\Finder\PasskeyFinder;
use XF\Http\Request;
use XF\Service\AbstractService;
use XF\Session\Session;
use XF\Util\Ip;

class ManagerService extends AbstractService
{
	protected $challenge;
	protected $challengeTime;

	/**
	 * @var Passkey
	 */
	protected $passkey;

	/**
	 * @var array
	 */
	protected $payload;

	public function __construct(App $app, ?Session $session = null)
	{
		parent::__construct($app);

		if ($session)
		{
			$this->setupFromSession($session);
		}
		else
		{
			$this->generateState();
		}
	}

	public function getChallenge(): string
	{
		return $this->challenge;
	}

	public function getPasskeyUser(): ?User
	{
		if (!$this->passkey)
		{
			throw new \LogicException('Passkey not validated');
		}

		return $this->passkey->User;
	}

	public function generateState(): void
	{
		$this->challenge = \XF::generateRandomString(128);
		$this->challengeTime = \XF::$time;
	}

	public function setupFromSession(Session $session): void
	{
		$values = $session->get('passkeyChallenge');
		if ($values)
		{
			$this->challenge = $values['challenge'];
			$this->challengeTime = $values['time'];
		}
		else
		{
			$this->generateState();
		}
	}

	public function saveStateToSession(Session $session): void
	{
		$session->set('passkeyChallenge', [
			'challenge' => $this->challenge,
			'time' => $this->challengeTime,
		]);
	}

	public function clearStateFromSession(Session $session): void
	{
		$session->remove('passkeyChallenge');
	}

	public function validate(Request $request, &$error = null): bool
	{
		if (!$this->verifyRequest($request, $error))
		{
			return false;
		}

		$payload = $this->payload;

		$clientDataJSON = base64_decode($payload['clientDataJSON']);
		$authenticatorData = base64_decode($payload['authenticatorData']);
		if (!$clientDataJSON || !$authenticatorData)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$credentialId = $payload['id'];
		$signature = $payload['signature'];

		$webAuthn = $this->getWebAuthnClass();

		$this->passkey = \XF::app()->finder(PasskeyFinder::class)
			->where('credential_id', $credentialId)
			->fetchOne();

		if (!$this->passkey)
		{
			$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
			return false;
		}

		$isValid = $webAuthn->processGet(
			$clientDataJSON,
			$authenticatorData,
			base64_decode($signature),
			$this->passkey->credential_public_key,
			$this->challenge
		);

		if (!$isValid)
		{
			$error = \XF::phrase('given_passkey_or_security_key_could_not_be_verified');
			return false;
		}

		$this->updatePasskeyLastUse($this->passkey, $request);

		return true;
	}

	public function create(Request $request, &$error = null)
	{
		if (!$this->verifyRequest($request, $error))
		{
			return false;
		}

		$name = $request->filter('passkey_name', '?str') ?: null;
		$userVerification = $request->filter('user_verification', '?str') ?: 'none';

		$payload = $this->payload;

		$clientDataJSON = base64_decode($payload['clientDataJSON']);
		$attestationObject = base64_decode($payload['attestationObject']);
		if (!$clientDataJSON || !$attestationObject)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$webAuthn = $this->getWebAuthnClass();

		try
		{
			$results = $webAuthn->processCreate(
				$clientDataJSON,
				$attestationObject,
				$this->challenge,
				($userVerification === 'required'),
				true,
				false
			);

			$encodedCredentialId = base64_encode($results->credentialId);
			$aaguid = bin2hex($results->AAGUID);

			$aaguidData = $this->app->data(\XF\Data\WebAuthn::class);
			$aaguidName = $aaguidData->getDataForAAGUID($aaguid)['name'] ?? null;

			$visitor = \XF::visitor();
			$visitorLang = $this->app->userLanguage($visitor);

			$fallbackName = \XF::phrase('passkey_date_x', [
				'date' => $visitorLang->dateTime(\XF::$time),
			]);

			/** @var Passkey $passkey */
			$passkey = \XF::em()->create(Passkey::class);
			$passkey->bulkSet([
				'user_id' => $visitor->user_id,
				'credential_id' => $encodedCredentialId,
				'credential_public_key' => $results->credentialPublicKey,
				'create_date' => \XF::$time,
				'create_ip_address' => Ip::stringToBinary($request->getIp()),
				'name' => $aaguidName ?? $name ?? $fallbackName,
				'aaguid' => $aaguid,
			]);
			$passkey->save();
		}
		catch (\Exception $e)
		{
			\XF::logError('Passkey registration failed: ' . $e->getMessage());

			$error = \XF::phrase('unexpected_error_occurred');
			return false;
		}

		return true;
	}

	protected function verifyRequest(Request $request, &$error = null): bool
	{
		if (!$this->challengeTime || \XF::$time - $this->challengeTime > 900)
		{
			$error = \XF::phrase('page_no_longer_available_back_try_again');
			return false;
		}

		if (!$this->challenge || $this->challenge !== $request->filter('webauthn_challenge', 'str'))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		$this->payload = $request->filter('webauthn_payload', 'json-array');
		if (!$this->payload)
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		return true;
	}

	protected function updatePasskeyLastUse(Passkey $passkey, Request $request): bool
	{
		$passkey->last_use_date = \XF::$time;
		$passkey->last_use_ip_address = Ip::stringToBinary($request->getIp());
		$passkey->save();

		return true;
	}

	protected function getWebAuthnClass(): WebAuthn
	{
		$options = \XF::options();

		return new WebAuthn(
			$options->boardTitle,
			parse_url($options->boardUrl, PHP_URL_HOST)
		);
	}
}
