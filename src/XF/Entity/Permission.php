<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Job\PermissionRebuild;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AddOnRepository;

/**
 * COLUMNS
 * @property string $permission_id
 * @property string $permission_group_id
 * @property string $permission_type
 * @property string $interface_group_id
 * @property string $depend_permission_id
 * @property int $display_order
 * @property string $addon_id
 *
 * GETTERS
 * @property-read Phrase $title
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 * @property-read PermissionInterfaceGroup|null $Interface
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 */
class Permission extends Entity
{
	/**
	 * @return Phrase
	 */
	public function getTitle()
	{
		return \XF::phrase($this->getPhraseName());
	}

	public function getPhraseName()
	{
		return 'permission.' . $this->permission_group_id . '_' . $this->permission_id;
	}

	public function getMasterPhrase()
	{
		$phrase = $this->MasterTitle;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->addon_id = $this->_getDeferredValue(function () { return $this->addon_id; });
			$phrase->title = $this->_getDeferredValue(function () { return $this->getPhraseName(); });
			$phrase->language_id = 0;
		}

		return $phrase;
	}

	protected function _preSave()
	{
		if ($this->isChanged('permission_id') || $this->isChanged('permission_group_id'))
		{
			$perm = $this->_em->find(Permission::class, [
				'permission_group_id' => $this->permission_group_id,
				'permission_id' => $this->permission_id,
			]);
			if ($perm)
			{
				$this->error(\XF::phrase('permission_ids_must_be_unique_within_groups'), 'permission_id');
			}
		}

		if ($this->getOption('dependent_check') && $this->depend_permission_id)
		{
			if ($this->isChanged('depend_permission_id') || $this->isChanged('permission_group_id'))
			{
				$perm = $this->_em->find(Permission::class, [
					'permission_group_id' => $this->permission_group_id,
					'permission_id' => $this->depend_permission_id,
				]);
				if (!$perm)
				{
					$this->error(\XF::phrase('please_enter_valid_dependent_permission_id'), 'depend_permission_id');
				}
			}
		}
	}

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged(['addon_id', 'permission_group_id', 'permission_id']))
			{
				/** @var Phrase $phrase */
				$phrase = $this->getExistingRelation('MasterTitle');
				if ($phrase)
				{
					$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');
					$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$phrase->addon_id = $this->addon_id;
					$phrase->title = $this->getPhraseName();
					$phrase->save();
				}
			}

			if ($this->isChanged(['permission_group_id', 'permission_id']))
			{
				$update = [
					'permission_group_id' => $this->permission_group_id,
					'permission_id' => $this->permission_id,
				];
				$this->db()->update(
					'xf_permission_entry',
					$update,
					'permission_group_id = ? AND permission_id = ?',
					[$this->getExistingValue('permission_group_id'), $this->getExistingValue('permission_id')]
				);
				$this->db()->update(
					'xf_permission_entry_content',
					$update,
					'permission_group_id = ? AND permission_id = ?',
					[$this->getExistingValue('permission_group_id'), $this->getExistingValue('permission_id')]
				);

				$this->enqueueRebuild();
			}
		}

		if ($this->isInsert())
		{
			$this->enqueueRebuild();
		}
	}

	protected function _postDelete()
	{
		$phrase = $this->MasterTitle;
		if ($phrase)
		{
			$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');
			$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

			$phrase->delete();
		}

		$this->db()->delete(
			'xf_permission_entry',
			'permission_group_id = ? AND permission_id = ?',
			[$this->permission_group_id, $this->permission_id]
		);
		$this->db()->delete(
			'xf_permission_entry_content',
			'permission_group_id = ? AND permission_id = ?',
			[$this->permission_group_id, $this->permission_id]
		);

		$this->enqueueRebuild();
	}

	protected function enqueueRebuild()
	{
		\XF::runOnce('xfPermissionRebuild', function ()
		{
			$this->app()->jobManager()->enqueueUnique('permissionRebuild', PermissionRebuild::class);
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
		$structure->table = 'xf_permission';
		$structure->shortName = 'XF:Permission';
		$structure->primaryKey = ['permission_group_id', 'permission_id'];
		$structure->columns = [
			'permission_id' => ['type' => self::STR, 'maxLength' => 25,
				'required' => 'please_enter_valid_permission_id',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'permission_group_id' => ['type' => self::STR, 'maxLength' => 25, 'required' => true,
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'permission_type' => ['type' => self::STR, 'default' => 'flag',
				'allowedValues' => ['flag', 'integer'],
			],
			'interface_group_id' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'depend_permission_id' => ['type' => self::STR, 'maxLength' => 25, 'default' => ''],
			'display_order' => ['type' => self::UINT, 'default' => 1],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'title' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
			'Interface' => [
				'entity' => 'XF:PermissionInterfaceGroup',
				'type' => self::TO_ONE,
				'conditions' => 'interface_group_id',
			],
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'permission.', '$permission_group_id', '_', '$permission_id'],
				],
			],
		];
		$structure->options = [
			'dependent_check' => true,
		];

		return $structure;
	}
}
