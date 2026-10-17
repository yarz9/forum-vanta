<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\NodePermissionPlugin;
use XF\Entity\User;
use XF\Entity\UserGroup;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\NodeRepository;
use XF\Repository\PermissionCombinationRepository;
use XF\Repository\PermissionEntryRepository;
use XF\Repository\PermissionRepository;
use XF\Repository\UserGroupRepository;
use XF\Service\UpdatePermissionsService;

use function strlen;

class PermissionController extends AbstractController
{
	public function actionUserGroup(ParameterBag $params)
	{
		$this->assertAdminPermission('userGroup');

		if ($params->user_group_id)
		{
			return $this->rerouteController(self::class, 'userGroupEdit', $params);
		}

		$viewParams = [
			'userGroups' => $this->getUserGroupRepo()->findUserGroupsForList()->fetch(),
		];
		return $this->view('XF:Permissions\UserGroupList', 'permissions_user_group_list', $viewParams);
	}

	public function actionUserGroupEdit(ParameterBag $params)
	{
		$this->assertAdminPermission('userGroup');

		$userGroup = $this->assertUserGroupExists($params->user_group_id);

		$permissionData = $this->getPermissionRepo()->getGlobalPermissionListData();
		$permissionData['values'] = $this->getEntryRepo()->getGlobalUserGroupPermissionEntries($userGroup->user_group_id);

		$viewParams = [
			'userGroup' => $userGroup,
			'permissionData' => $permissionData,
		];
		return $this->view('XF:Permissions\UserGroupEdit', 'permissions_user_group_edit', $viewParams);
	}

	public function actionUserGroupSave(ParameterBag $params)
	{
		$this->assertAdminPermission('userGroup');

		$this->assertPostOnly();

		$userGroup = $this->assertUserGroupExists($params->user_group_id);

		/** @var UpdatePermissionsService $permissionUpdater */
		$permissionUpdater = $this->service(UpdatePermissionsService::class);
		$permissions = $this->filter('permissions', 'array');

		$permissionUpdater->setUserGroup($userGroup)->setGlobal();
		$permissionUpdater->updatePermissions($permissions);

		return $this->redirect($this->buildLink('permissions/user-groups'));
	}

	public function actionUser(ParameterBag $params)
	{
		$this->assertAdminPermission('user');

		if ($params->user_id)
		{
			return $this->rerouteController(self::class, 'useredit', $params);
		}

		$username = $this->filter('username', 'str');
		if ($username)
		{
			$user = $this->em()->findOne(User::class, ['username' => $username]);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'), 404);
			}

			return $this->redirect($this->buildLink('permissions/users', $user));
		}

		$entries = $this->getEntryRepo()->getGlobalPermissionEntriesGrouped();

		if ($entries['users'])
		{
			$users = $this->finder(UserFinder::class)->whereIds(array_keys($entries['users']))->order('username')->fetch();
		}
		else
		{
			$users = [];
		}

		$viewParams = [
			'users' => $users,
		];
		return $this->view('XF:Permission\UserList', 'permission_user_list', $viewParams);
	}

	public function actionUserEdit(ParameterBag $params)
	{
		$this->assertAdminPermission('user');

		$user = $this->assertUserExists($params->user_id);

		$permissionData = $this->getPermissionRepo()->getGlobalPermissionListData();
		$userEntries = $this->getEntryRepo()->getGlobalUserPermissionEntries($user->user_id);

		$viewParams = [
			'user' => $user,
			'permissionData' => $permissionData,
			'userEntries' => $userEntries,
			'tab' => $this->filter('tab', 'bool'),
		];
		return $this->view('XF:Permission\UserEdit', 'permission_user_edit', $viewParams);
	}

	public function actionUserSave(ParameterBag $params)
	{
		$this->assertAdminPermission('user');

		$this->assertPostOnly();

		$user = $this->assertUserExists($params->user_id);

		/** @var UpdatePermissionsService $permissionUpdater */
		$permissionUpdater = $this->service(UpdatePermissionsService::class);
		$permissions = $this->filter('permissions', 'array');

		$permissionUpdater->setUser($user)->setGlobal();
		$permissionUpdater->updatePermissions($permissions);

		if ($this->filter('from-user-edit', 'bool'))
		{
			return $this->redirect($this->buildLink('users/edit', $user) . '#user-permissions');
		}

		return $this->redirect($this->buildLink('permissions/users'));
	}

	protected function getNodePermissionPlugin()
	{
		/** @var NodePermissionPlugin $plugin */
		$plugin = $this->plugin(NodePermissionPlugin::class);
		$plugin->setFormatters('XF:Permission\Node%s', 'permission_node_%s');
		$plugin->setRoutePrefix('permissions/nodes');

		return $plugin;
	}

	public function actionNode(ParameterBag $params)
	{
		$this->assertAdminPermission('node');

		if ($params->node_id)
		{
			return $this->getNodePermissionPlugin()->actionList($params);
		}
		else
		{
			$nodeRepo = $this->repository(NodeRepository::class);
			$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

			$customPermissions = $this->repository(PermissionEntryRepository::class)->getContentWithCustomPermissions('node');

			$viewParams = [
				'nodeTree' => $nodeTree,
				'customPermissions' => $customPermissions,
			];
			return $this->view('XF:Permission\NodeOverview', 'permission_node_overview', $viewParams);
		}
	}

	public function actionNodeEdit(ParameterBag $params)
	{
		$this->assertAdminPermission('node');

		return $this->getNodePermissionPlugin()->actionEdit($params);
	}

	public function actionNodeSave(ParameterBag $params)
	{
		$this->assertAdminPermission('node');

		return $this->getNodePermissionPlugin()->actionSave($params);
	}

	public function actionAnalyze()
	{
		$this->assertAdminPermission('user');

		$this->setSectionContext('analyzePermissions');

		$username = $this->filter('username', 'str');
		$contentType = $this->filter('content_type', 'str');
		$contentId = $this->filter('content_id', 'uint');

		$builder = $this->app()->permissionBuilder();

		$user = null;
		$analysis = null;

		if ($this->isPost() && strlen($username))
		{
			$user = $this->em()->findOne(User::class, ['username' => $username]);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}

			/** @var PermissionCombinationRepository $combinationRepo */
			$combinationRepo = $this->repository(PermissionCombinationRepository::class);
			$combination = $combinationRepo->getPermissionCombinationForUser($user);

			if ($contentType)
			{
				if ($builder->isValidPermissionContentType($contentType) && $contentId)
				{
					$analysis = $builder->analyzeCombinationContent($combination, $contentType, $contentId);
				}
			}
			else
			{
				// global permissions
				$analysis = $builder->analyzeCombination($combination);
			}
		}

		if ($analysis)
		{
			$permissionData = $this->getPermissionRepo()->getGlobalPermissionListData();

			/** @var UserGroupRepository $userGroupRepo */
			$userGroupRepo = $this->repository(UserGroupRepository::class);
			$userGroupTitles = $userGroupRepo->getUserGroupTitlePairs();
		}
		else
		{
			$permissionData = null;
			$userGroupTitles = null;
		}

		$viewParams = [
			'username' => $username,
			'contentType' => $contentType,
			'contentId' => $contentId,

			'user' => $user,
			'analysis' => $analysis,
			'permissionData' => $permissionData,
			'userGroupTitles' => $userGroupTitles,

			'contentOptions' => $builder->getAnalysisTypeData(),
		];
		return $this->view('XF:Permission\Analyze', 'permission_analyze', $viewParams);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return User
	 */
	protected function assertUserExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(User::class, $id, $with, $phraseKey);
	}

	/**
	 * @return PermissionRepository
	 */
	protected function getPermissionRepo()
	{
		return $this->repository(PermissionRepository::class);
	}

	/**
	 * @return PermissionEntryRepository
	 */
	protected function getEntryRepo()
	{
		return $this->repository(PermissionEntryRepository::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return UserGroup
	 */
	protected function assertUserGroupExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(UserGroup::class, $id, $with, $phraseKey);
	}

	/**
	 * @return UserGroupRepository
	 */
	protected function getUserGroupRepo()
	{
		return $this->repository(UserGroupRepository::class);
	}
}
