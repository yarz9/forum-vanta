<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $oauth_request_id
 * @property string $client_id
 * @property int $user_id
 * @property string $response_type
 * @property string $redirect_uri
 * @property string|null $state
 * @property string|null $code_challenge
 * @property string|null $code_challenge_method
 * @property int $request_date
 * @property array $scopes
 *
 * RELATIONS
 * @property-read OAuthClient|null $OAuthClient
 * @property-read User|null $User
 */
class OAuthRequest extends Entity
{
	use TokenScopeTrait;

	public function isValid(): bool
	{
		if (!$this->OAuthClient)
		{
			return false;
		}

		return true;
	}

	public function getRedirectUriSnippet(): string
	{
		$uri = $this->redirect_uri;
		return parse_url($uri, PHP_URL_SCHEME) . '://' . parse_url($uri, PHP_URL_HOST);
	}

	protected function _preSave()
	{
		if ($this->isInsert())
		{
			$this->oauth_request_id = \XF::generateRandomString(32);
		}
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_oauth_request';
		$structure->shortName = 'XF:OAuthRequest';
		$structure->primaryKey = 'oauth_request_id';
		$structure->columns = [
			'oauth_request_id' => ['type' => self::STR],
			'client_id' => ['type' => self::STR, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'response_type' => ['type' => self::STR, 'required' => true],
			'redirect_uri' => ['type' => self::STR, 'required' => true],
			'state' => ['type' => self::STR, 'nullable' => true],
			'code_challenge' => ['type' => self::STR, 'nullable' => true],
			'code_challenge_method' => ['type' => self::STR, 'nullable' => true],
			'request_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->relations = [
			'OAuthClient' => [
				'entity' => OAuthClient::class,
				'type' => self::TO_ONE,
				'conditions' => 'client_id',
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		$structure->defaultWith = ['OAuthClient'];

		static::addTokenScopeStructureElements($structure);

		return $structure;
	}
}
