<?php

namespace XF\Entity;

use XF\Data\WebAuthn;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\PasskeyRepository;

/**
 * COLUMNS
 * @property int $passkey_id
 * @property string $credential_id
 * @property string $credential_public_key
 * @property int $user_id
 * @property string $name
 * @property string|null $aaguid
 * @property int $create_date
 * @property string $create_ip_address
 * @property int $last_use_date
 * @property string $last_use_ip_address
 *
 * RELATIONS
 * @property-read User|null $User
 */
class Passkey extends Entity
{
	public function hasIcon(): bool
	{
		$aaguid = $this->aaguid;

		if (!$aaguid)
		{
			return false;
		}

		$webAuthnData = $this->app()->data(WebAuthn::class);
		$aaguidData = $webAuthnData->getDataForAAGUID($aaguid);

		return (bool) ($aaguidData['icon_light'] ?? false);
	}

	public function getIcon(string $variant): ?string
	{
		$aaguid = $this->aaguid;

		if (!$aaguid)
		{
			return null;
		}

		$webAuthnData = $this->app()->data(WebAuthn::class);
		$aaguidData = $webAuthnData->getDataForAAGUID($aaguid);

		return $aaguidData['icon_' . $variant] ?? null;
	}

	public function getAuthenticatorName(): string
	{
		$aaguid = $this->aaguid;

		if (!$aaguid)
		{
			return '';
		}

		$webAuthnData = $this->app()->data(WebAuthn::class);
		$aaguidData = $webAuthnData->getDataForAAGUID($aaguid);

		return $aaguidData['name'] ?? '';
	}

	protected function _postSave(): void
	{
		$this->updateUserPasskeyProvider();
	}

	protected function _postDelete(): void
	{
		$this->updateUserPasskeyProvider();
	}

	protected function updateUserPasskeyProvider(): void
	{
		$repo = $this->repository(PasskeyRepository::class);

		\XF::runOnce('updateUserPasskeyProvider-' . $this->user_id, function () use ($repo)
		{
			$repo->updateUserPasskeyProvider($this->User);
		});
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_passkey';
		$structure->shortName = 'XF:Passkey';
		$structure->primaryKey = 'passkey_id';
		$structure->columns = [
			'passkey_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'credential_id' => ['type' => self::STR, 'required' => true],
			'credential_public_key' => ['type' => self::STR, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'name' => ['type' => self::STR, 'required' => true],
			'aaguid' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'create_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'create_ip_address' => ['type' => self::BINARY, 'maxLength' => 16, 'default' => ''],
			'last_use_date' => ['type' => self::UINT, 'default' => 0],
			'last_use_ip_address' => ['type' => self::BINARY, 'maxLength' => 16, 'default' => ''],
		];
		$structure->relations = [
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
