<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $token_id
 * @property string $token
 * @property string $client_id
 * @property int $user_id
 * @property int $issue_date
 * @property int $last_use_date
 * @property int $expiry_date
 * @property int $revoked_date
 * @property array $scopes
 *
 * RELATIONS
 * @property-read OAuthClient|null $OAuthClient
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\OAuthRefreshToken> $OAuthRefreshTokens
 * @property-read User|null $User
 */
class OAuthToken extends Entity
{
	use TokenScopeTrait;

	public const TOKEN_LIFETIME_SECONDS = 60 * 60 * 2; // 2 hours

	public function getNewRefreshToken(): OAuthRefreshToken
	{
		/** @var OAuthRefreshToken $refreshToken */
		$refreshToken = $this->_em->create(OAuthRefreshToken::class);

		$refreshToken->token_id = $this->_getDeferredValue(function ()
		{
			return $this->token_id;
		}, 'save');

		return $refreshToken;
	}

	public function isValid(): bool
	{
		if (!$this->OAuthClient || !$this->User)
		{
			return false;
		}

		if ($this->revoked_date)
		{
			return false;
		}

		return $this->expiry_date > \XF::$time;
	}

	public function generateToken(): string
	{
		return \XF::generateRandomString(32);
	}

	protected function _preSave(): void
	{
		if ($this->isInsert())
		{
			$this->token = $this->generateToken();

			if (!$this->expiry_date)
			{
				$this->expiry_date = \XF::$time + self::TOKEN_LIFETIME_SECONDS;
			}
		}
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_oauth_token';
		$structure->shortName = 'XF:OAuthToken';
		$structure->primaryKey = 'token_id';
		$structure->columns = [
			'token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'token' => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
			'client_id' => ['type' => self::STR, 'maxLength' => 16, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'issue_date' => ['type' => self::UINT, 'required' => true, 'default' => \XF::$time],
			'last_use_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'expiry_date' => ['type' => self::UINT, 'required' => true],
			'revoked_date' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'OAuthClient' => [
				'entity' => OAuthClient::class,
				'type' => self::TO_ONE,
				'conditions' => 'client_id',
			],
			'OAuthRefreshTokens' => [
				'entity' => OAuthRefreshToken::class,
				'type' => self::TO_MANY,
				'conditions' => 'token_id',
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		static::addTokenScopeStructureElements($structure);

		return $structure;
	}
}
