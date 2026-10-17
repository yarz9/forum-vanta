<?php

namespace XF\Permission;

use XF\Entity\Permission;
use XF\Entity\PermissionCombination;
use XF\Repository\PermissionEntryRepository;

abstract class AbstractContentPermissions
{
	/**
	 * @var Builder|null
	 */
	protected $builder;

	protected $permissionsGrouped;

	protected $userEntries;
	protected $groupEntries;
	protected $systemEntries;

	public function __construct(Builder $builder)
	{
		$this->builder = $builder;
		$this->setupBuildData();
	}

	abstract protected function getContentType();

	abstract public function rebuildCombination(PermissionCombination $combination, array $basePerms);

	abstract public function analyzeCombination(
		PermissionCombination $combination,
		$contentId,
		array $basePerms,
		array $baseIntermediates
	);

	abstract public function getAnalysisTypeTitle();

	abstract public function getAnalysisContentPairs();

	abstract public function isValidPermission(Permission $permission);

	public function setupBuildData()
	{
		/** @var PermissionEntryRepository $entryRepo */
		$entryRepo = $this->builder->em()->getRepository(PermissionEntryRepository::class);

		$entries = $entryRepo->getContentPermissionEntriesGrouped($this->getContentType());
		$this->userEntries = $entries['users'];
		$this->groupEntries = $entries['groups'];
		$this->systemEntries = $entries['system'];

		$this->setupBuildTypeData();

		$this->permissionsGrouped = $this->filterAvailablePermissions($this->builder->getPermissionsGrouped());
	}

	protected function setupBuildTypeData()
	{
	}

	protected function filterAvailablePermissions(array $permissionsGrouped)
	{
		foreach ($permissionsGrouped AS $groupId => &$permissions)
		{
			foreach ($permissions AS $permissionId => $permission)
			{
				if (!$this->isValidPermission($permission))
				{
					unset($permissions[$permissionId]);
				}
			}
			if (!$permissions)
			{
				unset($permissionsGrouped[$groupId]);
			}
		}

		return $permissionsGrouped;
	}

	protected function writeBuiltCombination(PermissionCombination $combination, array $built)
	{
		$db = $this->builder->db();
		$combinationId = $combination->permission_combination_id;
		$contentType = $this->getContentType();

		$insert = [];
		foreach ($built AS $contentId => $cache)
		{
			$insert[] = [
				'permission_combination_id' => $combinationId,
				'content_type' => $contentType,
				'content_id' => $contentId,
				'cache_value' => json_encode($cache),
			];
		}

		$db->delete(
			'xf_permission_cache_content',
			'permission_combination_id = ? AND content_type = ?',
			[$combinationId, $contentType]
		);
		if ($insert)
		{
			$db->insertBulk('xf_permission_cache_content', $insert);
		}
	}

	public function getApplicablePermissionSets($contentId, array $userGroupIds, $userId = 0)
	{
		$sets = [];
		foreach ($userGroupIds AS $userGroupId)
		{
			if (isset($this->groupEntries[$contentId][$userGroupId]))
			{
				$sets["group-$userGroupId"] = $this->groupEntries[$contentId][$userGroupId];
			}
		}
		if ($userId && isset($this->userEntries[$contentId][$userId]))
		{
			$sets["user-$userId"] = $this->userEntries[$contentId][$userId];
		}
		if (isset($this->systemEntries[$contentId]))
		{
			$sets['system'] = $this->systemEntries[$contentId];
		}

		return $sets;
	}

	protected function adjustBasePermissionAllows(array $basePermissions)
	{
		foreach ($basePermissions AS $group => $p)
		{
			foreach ($p AS $id => $value)
			{
				if ($value === 'content_allow')
				{
					$basePermissions[$group][$id] = 'allow';
				}
			}
		}

		return $basePermissions;
	}

	public function getAvailablePermissions()
	{
		return $this->permissionsGrouped;
	}
}
