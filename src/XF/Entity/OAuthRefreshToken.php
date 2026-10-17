<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $refresh_token_id
 * @property int $token_id
 * @property string $refresh_token
 * @property string $client_id
 * @property int $issue_date
 * @property int $expiry_date
 * @property int $revoked_date
 *
 * RELATIONS
 * @property-read OAuthClient|null $OAuthClient
 * @property-read OAuthToken|null $OAuthToken
 */
class OAuthRefreshToken extends Entity
{
	public const TOKEN_LIFETIME_SECONDS = 60 * 60 * 24 * 90; // 90 days

	public function isValid(): bool
	{
		if (!$this->OAuthClient || !$this->OAuthToken)
		{
			return false;
		}

		if ($this->revoked_date)
		{
			return false;
		}

		return $this->expiry_date > \XF::$time;
	}

	public function revoke(): bool
	{
		$this->revoked_date = \XF::$time;
		$this->save();

		return true;
	}

	public function generateRefreshToken(): string
	{
		return \XF::generateRandomString(32);
	}

	protected function _preSave(): void
	{
		if ($this->isInsert())
		{
			$this->refresh_token = $this->generateRefreshToken();

			if (!$this->expiry_date)
			{
				$this->expiry_date = \XF::$time + self::TOKEN_LIFETIME_SECONDS;
			}
		}
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_oauth_refresh_token';
		$structure->shortName = 'XF:OAuthRefreshToken';
		$structure->primaryKey = 'refresh_token_id';
		$structure->columns = [
			'refresh_token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'token_id' => ['type' => self::UINT, 'required' => true],
			'refresh_token' => ['type' => self::STR, 'required' => true],
			'client_id' => ['type' => self::STR, 'required' => true],
			'issue_date' => ['type' => self::UINT, 'required' => true, 'default' => \XF::$time],
			'expiry_date' => ['type' => self::UINT, 'required' => true],
			'revoked_date' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'OAuthClient' => [
				'entity' => OAuthClient::class,
				'type' => self::TO_ONE,
				'conditions' => 'client_id',
				'primary' => true,
			],
			'OAuthToken' => [
				'entity' => OAuthToken::class,
				'type' => self::TO_ONE,
				'conditions' => 'token_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		return $structure;
	}
}
