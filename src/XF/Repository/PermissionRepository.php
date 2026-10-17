<?php

namespace XF\Repository;

use XF\Finder\PermissionFinder;
use XF\Finder\PermissionInterfaceGroupFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Permission\AbstractContentPermissions;

class PermissionRepository extends Repository
{
	public function getGlobalPermissionListData()
	{
		$permissions = $this->findPermissionsForList()->fetch();

		return [
			'interfaceGroups' => $this->findInterfaceGroupsForList()->fetch(),
			'permissionsGrouped' => $permissions->groupBy('interface_group_id'),
			'permissionsGroupedType' => $permissions->groupBy('permission_group_id', 'permission_id'),
		];
	}

	public function getContentPermissionListData($contentType)
	{
		$contentHandler = $this->getPermissionHandler($contentType);
		if (!$contentHandler)
		{
			throw new \InvalidArgumentException("No permission handler for $contentType");
		}

		$permissions = $this->findPermissionsForList()->fetch();
		$permissions = $permissions->filter(function ($p) use ($contentHandler)
		{
			return $contentHandler->isValidPermission($p);
		});

		return [
			'interfaceGroups' => $this->findInterfaceGroupsForList()->fetch(),
			'permissionsGrouped' => $permissions->groupBy('interface_group_id'),
			'permissionsGroupedType' => $permissions->groupBy('permission_group_id', 'permission_id'),
		];
	}

	/**
	 * @return Finder
	 */
	public function findPermissionsForList()
	{
		return $this->finder(PermissionFinder::class)
			->whereAddOnActive()
			->order(['display_order', 'permission_id']);
	}

	public function getPermissionsGrouped()
	{
		$permissions = $this->finder(PermissionFinder::class)
			->order(['permission_group_id', 'permission_id'])->fetch();
		return $permissions->groupBy('permission_group_id', 'permission_id');
	}

	/**
	 * @return Finder
	 */
	public function findInterfaceGroupsForList()
	{
		return $this->finder(PermissionInterfaceGroupFinder::class)
			->whereAddOnActive()
			->order(['display_order', 'interface_group_id']);
	}

	/**
	 * @return AbstractContentPermissions[]
	 */
	public function getPermissionHandlers()
	{
		return $this->app()->permissionBuilder()->getContentHandlers();
	}

	/**
	 * @param string $type
	 *
	 * @return AbstractContentPermissions|null
	 */
	public function getPermissionHandler($type)
	{
		return $this->app()->permissionBuilder()->getContentHandler($type);
	}
}
