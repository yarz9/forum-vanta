<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\AddOnRepository;
use XF\Repository\ContentTypeFieldRepository;

/**
 * COLUMNS
 * @property string $content_type
 * @property string $field_name
 * @property string $field_value
 * @property string $addon_id
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class ContentTypeField extends Entity
{
	protected function _preSave()
	{
		if ($this->isInsert())
		{
			$exists = $this->em()->findOne(ContentTypeField::class, [
				'content_type' => $this->content_type,
				'field_name' => $this->field_name,
			]);
			if ($exists)
			{
				$this->error(\XF::phrase('please_enter_unique_content_type_field_name_combination'));
			}
		}
	}

	protected function _postSave()
	{
		$this->rebuildFieldCache();
	}

	protected function _postDelete()
	{
		$this->rebuildFieldCache();
	}

	protected function rebuildFieldCache()
	{
		$repo = $this->getFieldRepo();

		\XF::runOnce('contentTypeCacheRebuild', function () use ($repo)
		{
			$repo->rebuildContentTypeCache();
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
		$structure->table = 'xf_content_type_field';
		$structure->shortName = 'XF:ContentTypeField';
		$structure->primaryKey = ['content_type', 'field_name'];
		$structure->columns = [
			'content_type' => ['type' => self::BINARY, 'maxLength' => 25,
				'required' => 'please_enter_valid_content_type',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'field_name' => ['type' => self::BINARY, 'maxLength' => 50,
				'required' => 'please_enter_valid_field_name',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'field_value' => ['type' => self::BINARY, 'maxLength' => 75,
				'required' => 'please_enter_valid_value',
			],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return ContentTypeFieldRepository
	 */
	protected function getFieldRepo()
	{
		return $this->repository(ContentTypeFieldRepository::class);
	}
}
