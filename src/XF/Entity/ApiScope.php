<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AddOnRepository;
use XF\Repository\ApiRepository;

/**
 * COLUMNS
 * @property string $api_scope_id
 * @property bool $usable_with_oauth_clients
 * @property string $addon_id
 *
 * GETTERS
 * @property-read Phrase $description
 * @property-read mixed $api_scope_id_url
 * @property-read mixed $api_scope_id_phrase
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 */
class ApiScope extends Entity
{
	/**
	 * @return Phrase
	 */
	public function getDescription()
	{
		return \XF::phrase($this->getPhraseName());
	}

	public function getApiScopeIdUrl()
	{
		if (!$this->api_scope_id)
		{
			return '';
		}

		return str_replace(':', '-', $this->api_scope_id);
	}

	public function getApiScopeIdPhrase()
	{
		if (!$this->api_scope_id)
		{
			return '';
		}

		return str_replace(':', '_', $this->api_scope_id);
	}

	public function getPhraseName()
	{
		return 'api_scope.' . $this->api_scope_id_phrase;
	}

	public function getMasterPhrase()
	{
		$phrase = $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->addon_id = $this->_getDeferredValue(function () { return $this->addon_id; });
			$phrase->title = $this->_getDeferredValue(function () { return $this->getPhraseName(); });
			$phrase->language_id = 0;
		}

		return $phrase;
	}

	protected function verifyApiScopeId(&$id)
	{
		$id = strtolower($id);

		if (!preg_match('#^[a-z0-9_]+(:[a-z0-9_]+)*$#', $id))
		{
			$this->error(\XF::phrase('please_enter_valid_value'), 'api_scope_id');
			return false;
		}

		return true;
	}

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('api_scope_id'))
			{
				/** @var Phrase $phrase */
				$phrase = $this->getExistingRelation('MasterDescription');
				if ($phrase)
				{
					$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');
					$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$phrase->addon_id = $this->addon_id;
					$phrase->title = $this->getPhraseName();
					$phrase->save();
				}
			}

			if ($this->isChanged('api_scope_id'))
			{
				$this->db()->update(
					'xf_api_key_scope',
					['api_scope_id' => $this->api_scope_id],
					'api_scope_id = ?',
					$this->getExistingValue('api_scope_id')
				);

				$this->rebuildApiScopeCache();
			}
		}
	}

	protected function _postDelete()
	{
		$phrase = $this->MasterDescription;
		if ($phrase)
		{
			$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');
			$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

			$phrase->delete();
		}

		$this->db()->delete(
			'xf_api_key_scope',
			'api_scope_id = ?',
			$this->api_scope_id
		);

		$this->rebuildApiScopeCache();
	}

	protected function rebuildApiScopeCache()
	{
		$repo = $this->getApiRepo();

		\XF::runOnce('apiScopeCacheRebuild', function () use ($repo)
		{
			$repo->rebuildApiScopeCache();
		});
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_api_scope';
		$structure->shortName = 'XF:ApiScope';
		$structure->primaryKey = 'api_scope_id';
		$structure->columns = [
			'api_scope_id' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_api_scope_id',
				'unique' => 'api_scope_ids_must_be_unique',
			],
			'usable_with_oauth_clients' => ['type' => self::BOOL, 'default' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'description' => true,
			'api_scope_id_url' => true,
			'api_scope_id_phrase' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					[
						'title', '=', function ($context, $arg1)
						{
							if ($context == 'value')
							{
								/** @var ApiScope $entity */
								$entity = $arg1;
								return $entity->getPhraseName();
							}
							else
							{
								$joinTable = $arg1;
								return "CONCAT('api_scope.', REPLACE(`$joinTable`.`api_scope_id`, ':', '_'))";
							}
						},
					],
				],
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return ApiRepository
	 */
	protected function getApiRepo()
	{
		return $this->repository(ApiRepository::class);
	}
}
