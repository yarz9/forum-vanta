<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AddOnRepository;
use XF\Repository\AdminPermissionRepository;

/**
 * COLUMNS
 * @property string $admin_permission_id
 * @property int $display_order
 * @property string $addon_id
 *
 * GETTERS
 * @property-read Phrase $title
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 */
class AdminPermission extends Entity
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
		return 'admin_permission.' . $this->admin_permission_id;
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

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('admin_permission_id'))
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

			if ($this->isChanged('admin_permission_id'))
			{
				$this->db()->update(
					'xf_admin_permission_entry',
					['admin_permission_id' => $this->admin_permission_id],
					'admin_permission_id = ?',
					$this->getExistingValue('admin_permission_id')
				);

				$this->rebuildAdminPermissionCache();
			}
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
			'xf_admin_permission_entry',
			'admin_permission_id = ?',
			$this->admin_permission_id
		);

		$this->rebuildAdminPermissionCache();
	}

	protected function rebuildAdminPermissionCache()
	{
		$repo = $this->getPermissionRepo();

		\XF::runOnce('adminPermissionCacheRebuild', function () use ($repo)
		{
			$repo->rebuildAdminPermissionCache();
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
		$structure->table = 'xf_admin_permission';
		$structure->shortName = 'XF:AdminPermission';
		$structure->primaryKey = 'admin_permission_id';
		$structure->columns = [
			'admin_permission_id' => ['type' => self::STR, 'maxLength' => 25,
				'required' => 'please_enter_valid_permission_id',
				'unique' => 'admin_permission_ids_must_be_unique',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'display_order' => ['type' => self::UINT, 'default' => 0],
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
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'admin_permission.', '$admin_permission_id'],
				],
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return AdminPermissionRepository
	 */
	protected function getPermissionRepo()
	{
		return $this->repository(AdminPermissionRepository::class);
	}
}
