<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\OAuthRepository;
use XF\Validator\Url;

/**
 * COLUMNS
 * @property string $client_id
 * @property string $client_secret
 * @property string $client_type
 * @property string $title
 * @property string $description
 * @property string $image_url
 * @property string $homepage_url
 * @property array $redirect_uris
 * @property array $allowed_scopes
 * @property bool $active
 * @property int $creation_user_id
 * @property int $creation_date
 *
 * GETTERS
 * @property-read string $client_secret_snippet
 */
class OAuthClient extends Entity
{
	public function isUsable(): bool
	{
		return $this->active;
	}

	public function getNewAuthToken(): OAuthToken
	{
		/** @var OAuthToken $token */
		$token = $this->_em->create(OAuthToken::class);
		$token->client_id = $this->client_id;

		return $token;
	}

	public function getClientSecretSnippet(): string
	{
		return substr($this->client_secret, 0, 8) . '...';
	}

	public function generateClientId(): string
	{
		$length = 16;
		$clientId = '';

		while (true)
		{
			for ($i = 0; $i < $length; $i++)
			{
				$clientId .= mt_rand(0, 9);
			}

			$exists = $this->db()->fetchRow('
				SELECT client_id
				FROM xf_oauth_client
				WHERE client_id = ?
			', $clientId);

			if (!$exists)
			{
				break;
			}
		}

		return $clientId;
	}

	public function generateClientSecret(): string
	{
		return \XF::generateRandomString(32);
	}

	protected function verifyRedirectUris(&$value): bool
	{
		$urlValidator = \XF::app()->validator(Url::class);

		foreach ($value AS &$uri)
		{
			$uri = $urlValidator->coerceValue($uri);
			if (!$urlValidator->isValid($uri, $error))
			{
				$this->error(
					\XF::phrase('please_enter_valid_redirect_uris'),
					'redirect_uris'
				);
				return false;
			}
		}

		return true;
	}

	protected function _preSave(): void
	{
		if ($this->isInsert())
		{
			$this->client_id = $this->generateClientId();
			$this->client_secret = $this->generateClientSecret();

			if (!$this->creation_user_id)
			{
				$this->creation_user_id = \XF::visitor()->user_id;
			}
		}
	}

	protected function _postSave()
	{
		$this->rebuildOAuthClientCount();
	}

	protected function _postDelete(): void
	{
		$this->db()->delete('xf_oauth_request', 'client_id = ?', $this->client_id);
		$this->db()->delete('xf_oauth_token', 'client_id = ?', $this->client_id);

		$this->rebuildOAuthClientCount();
	}

	protected function rebuildOAuthClientCount(): void
	{
		\XF::runOnce('oAuthClientCountRebuild', function ()
		{
			$this->repository(OAuthRepository::class)->rebuildClientCount();
		});
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_oauth_client';
		$structure->shortName = 'XF:OAuthClient';
		$structure->primaryKey = 'client_id';
		$structure->columns = [
			'client_id' => ['type' => self::STR, 'maxLength' => 16, 'required' => true],
			'client_secret' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'client_type' => ['type' => self::STR, 'default' => 'confidential',
				'allowedValues' => ['confidential', 'public'],
			],
			'title' => ['type' => self::STR, 'required' => 'please_enter_valid_title', 'maxLength' => 50],
			'description' => ['type' => self::STR, 'default' => ''],
			'image_url' => ['type' => self::STR, 'default' => '', 'maxLength' => 200],
			'homepage_url' => ['type' => self::STR, 'default' => '',
				'match' => self::MATCH_URL_EMPTY, 'maxLength' => 200,
			],
			'redirect_uris' => ['type' => self::JSON_ARRAY, 'required' => 'please_enter_valid_redirect_uris', 'default' => []],
			'allowed_scopes' => ['type' => self::JSON_ARRAY, 'default' => []],
			'active' => ['type' => self::BOOL, 'default' => true],
			'creation_user_id' => ['type' => self::UINT, 'default' => 0],
			'creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->getters = [
			'client_secret_snippet' => true,
		];

		return $structure;
	}
}
