<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\Entity\UserGroup;
use XF\Mvc\ParameterBag;
use XF\Repository\PermissionEntryRepository;
use XF\Repository\PermissionRepository;
use XF\Repository\UserGroupRepository;
use XF\Service\UpdatePermissionsService;

class UserGroupController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('userGroup');
	}

	public function actionIndex()
	{
		$viewParams = [
			'userGroups' => $this->getUserGroupRepo()->findUserGroupsForList()->fetch(),
		];
		return $this->view('XF:UserGroup\Listing', 'user_group_list', $viewParams);
	}

	protected function userGroupAddEdit(UserGroup $userGroup)
	{
		$displayStyles = [
			'userBanner userBanner--hidden',
			'userBanner userBanner--primary',
			'userBanner userBanner--accent',
			'userBanner userBanner--red',
			'userBanner userBanner--green',
			'userBanner userBanner--olive',
			'userBanner userBanner--lightGreen',
			'userBanner userBanner--blue',
			'userBanner userBanner--royalBlue',
			'userBanner userBanner--skyBlue',
			'userBanner userBanner--gray',
			'userBanner userBanner--silver',
			'userBanner userBanner--yellow',
			'userBanner userBanner--orange',
		];

		/** @var PermissionRepository $permissionRepo */
		$permissionRepo = $this->repository(PermissionRepository::class);
		$permissionData = $permissionRepo->getGlobalPermissionListData();

		/** @var PermissionEntryRepository $entryRepo */
		$entryRepo = $this->repository(PermissionEntryRepository::class);
		$permissionData['values'] = $entryRepo->getGlobalUserGroupPermissionEntries($userGroup->user_group_id);

		$viewParams = [
			'userGroup' => $userGroup,
			'displayStyles' => $displayStyles,

			'permissionData' => $permissionData,
		];
		return $this->view('XF:UserGroup\Edit', 'user_group_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$userGroup = $this->assertUserGroupExists($params->user_group_id);
		return $this->userGroupAddEdit($userGroup);
	}

	public function actionAdd()
	{
		$userGroup = $this->em()->create(UserGroup::class);
		return $this->userGroupAddEdit($userGroup);
	}

	protected function userGroupSaveProcess(UserGroup $userGroup)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'display_style_priority' => 'uint',
			'username_css' => 'str',
			'banner_css_class' => 'str',
			'banner_text' => 'str',
		]);

		$input['user_title'] = $this->filter('user_title_override', 'bool')
			? $this->filter('user_title', 'str')
			: '';

		if (!$input['banner_css_class'])
		{
			$input['banner_css_class'] = $this->filter('banner_css_class_other', 'str');
		}

		$form->basicEntitySave($userGroup, $input);

		/** @var UpdatePermissionsService $permissionUpdater */
		$permissionUpdater = $this->service(UpdatePermissionsService::class);
		$permissions = $this->filter('permissions', 'array');

		$form->apply(function () use ($userGroup, $permissions, $permissionUpdater)
		{
			$permissionUpdater->setUserGroup($userGroup)->setGlobal();
			$permissionUpdater->updatePermissions($permissions);
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->user_group_id)
		{
			$userGroup = $this->assertUserGroupExists($params->user_group_id);
		}
		else
		{
			$userGroup = $this->em()->create(UserGroup::class);
		}

		$this->userGroupSaveProcess($userGroup)->run();

		return $this->redirect($this->buildLink('user-groups') . $this->buildLinkHash($userGroup->user_group_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$userGroup = $this->assertUserGroupExists($params->user_group_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$userGroup,
			$this->buildLink('user-groups/delete', $userGroup),
			$this->buildLink('user-groups/edit', $userGroup),
			$this->buildLink('user-groups'),
			$userGroup->title
		);
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
