<?php

namespace XF\Entity;

use XF\Finder\ModeratorContentFinder;
use XF\Finder\PermissionFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Service\UpdatePermissionsService;
use XF\Service\User\UserGroupChangeService;

/**
 * COLUMNS
 * @property int $user_id
 * @property bool $is_super_moderator
 * @property array $extra_user_group_ids
 * @property bool $notify_report
 * @property bool $notify_approval
 *
 * RELATIONS
 * @property-read User|null $User
 */
class Moderator extends Entity
{
	protected function _postSave()
	{
		if ($this->isChanged('extra_user_group_ids'))
		{
			$this->getUserGroupChangeService()->addUserGroupChange(
				$this->user_id,
				'moderator',
				$this->extra_user_group_ids
			);
		}

		if ($this->User)
		{
			$this->User->is_moderator = true;
			$this->User->save();
		}
	}

	protected function _postDelete()
	{
		$contentModerators = $this->finder(ModeratorContentFinder::class)
			->where('user_id', $this->user_id)
			->fetch();

		foreach ($contentModerators AS $contentModerator)
		{
			$contentModerator->delete(false);
		}

		if ($this->User)
		{
			$this->User->is_moderator = false;
			$this->User->is_staff = false;
			$this->User->save();

			$permissions = $this->finder(PermissionFinder::class)
				->where('Interface.is_moderator', 1)
				->where('permission_type', 'flag') // all that's supported
				->fetch();

			$permissionValues = [];
			foreach ($permissions AS $permission)
			{
				$permissionValues[$permission->permission_group_id][$permission->permission_id] = 'unset';
			}

			/** @var UpdatePermissionsService $permissionUpdater */
			$permissionUpdater = $this->app()->service(UpdatePermissionsService::class);
			$permissionUpdater->setUser($this->User)->setGlobal();
			$permissionUpdater->updatePermissions($permissionValues);
		}

		$this->getUserGroupChangeService()->removeUserGroupChange(
			$this->user_id,
			'moderator'
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_moderator';
		$structure->shortName = 'XF:Moderator';
		$structure->primaryKey = 'user_id';
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'is_super_moderator' => ['type' => self::BOOL, 'default' => false],
			'extra_user_group_ids' => ['type' => self::LIST_COMMA, 'default' => [],
				'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC],
			],
			'notify_report' => ['type' => self::BOOL, 'default' => false],
			'notify_approval' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}

	/**
	 * @return UserGroupChangeService
	 */
	protected function getUserGroupChangeService()
	{
		return $this->app()->service(UserGroupChangeService::class);
	}
}
