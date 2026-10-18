<?php

namespace XF\Import\Data;

use XF\Import\DataHelper\Permission;
use XF\Repository\UserGroupRepository;

/**
 * @mixin \XF\Entity\UserGroup
 */
class UserGroup extends AbstractEmulatedData
{
	protected $permissions = [];

	public function getImportType()
	{
		return 'user_group';
	}

	public function getEntityShortName()
	{
		return 'XF:UserGroup';
	}

	public function setPermissions(array $permissions)
	{
		$this->permissions = $permissions;
	}

	protected function postSave($oldId, $newId)
	{
		if ($this->permissions)
		{
			/** @var Permission $permissionHelper */
			$permissionHelper = $this->dataManager->helper(Permission::class);
			$permissionHelper->insertUserGroupPermissions($newId, $this->permissions);
		}

		/** @var UserGroupRepository $repo */
		$repo = $this->repository(UserGroupRepository::class);

		\XF::runOnce('rebuildUserGroupImport', function () use ($repo)
		{
			$repo->rebuildDisplayStyleCache();
			$repo->rebuildUserBannerCache();
		});
	}
}
