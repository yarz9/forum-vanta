<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $code
 * @property string $oauth_request_id
 * @property int $expiry_date
 *
 * RELATIONS
 * @property-read OAuthRequest|null $OAuthRequest
 */
class OAuthCode extends Entity
{
	public const CODE_LIFETIME_SECONDS = 60 * 5; // 5 minutes

	public function isValid(): bool
	{
		if (!$this->OAuthRequest || !$this->OAuthRequest->isValid())
		{
			return false;
		}

		return $this->expiry_date >= \XF::$time;
	}

	protected function generateCode(): string
	{
		while (true)
		{
			$code = \XF::generateRandomString(32);

			$exists = $this->db()->fetchRow('
				SELECT code
				FROM xf_oauth_code
				WHERE code = ?
			', $code);

			if (!$exists)
			{
				break;
			}
		}

		return $code;
	}

	protected function _preSave(): void
	{
		if ($this->isInsert())
		{
			$this->code = $this->generateCode();

			if (!$this->expiry_date)
			{
				$this->expiry_date = \XF::$time + self::CODE_LIFETIME_SECONDS;
			}
		}
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_oauth_code';
		$structure->shortName = 'XF:OAuthCode';
		$structure->primaryKey = 'code';
		$structure->columns = [
			'code' => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
			'oauth_request_id' => ['type' => self::STR, 'required' => true],
			'expiry_date' => ['type' => self::UINT, 'required' => true],
		];
		$structure->relations = [
			'OAuthRequest' => [
				'entity' => OAuthRequest::class,
				'type' => self::TO_ONE,
				'conditions' => 'oauth_request_id',
				'primary' => true,
			],
		];
		$structure->defaultWith = ['OAuthRequest'];

		return $structure;
	}
}
