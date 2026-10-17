<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\Entity\Permission;
use XF\Entity\PermissionInterfaceGroup;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\PermissionRepository;

class PermissionDefinitionController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertDevelopmentMode();
	}

	public function actionIndex()
	{
		$permissionRepo = $this->getPermissionRepo();
		$listData = $permissionRepo->getGlobalPermissionListData();

		$viewParams = [
			'interfaceGroups' => $listData['interfaceGroups'],
			'permissionsGrouped' => $listData['permissionsGrouped'],
		];
		return $this->view('XF:Permission\Listing', 'permission_definition_list', $viewParams);
	}

	protected function permissionAddEdit(Permission $permission)
	{
		$permissionRepo = $this->getPermissionRepo();

		$viewParams = [
			'permission' => $permission,
			'interfaceGroups' => $permissionRepo->findInterfaceGroupsForList()->fetch(),
		];
		return $this->view('XF:Permission\Edit', 'permission_definition_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$permission = $this->assertPermissionExists($params['permission_group_id'], $params['permission_id']);
		return $this->permissionAddEdit($permission);
	}

	public function actionAdd()
	{
		$permission = $this->em()->create(Permission::class);

		$interfaceGroupId = $this->filter('interface_group_id', 'str');
		if ($interfaceGroupId)
		{
			$permission->interface_group_id = $interfaceGroupId;
		}

		return $this->permissionAddEdit($permission);
	}

	protected function permissionSaveProcess(Permission $permission)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'permission_id' => 'str',
			'permission_group_id' => 'str',
			'permission_type' => 'str',
			'interface_group_id' => 'str',
			'display_order' => 'uint',
			'depend_permission_id' => 'str',
			'addon_id' => 'str',
		]);
		$form->basicEntitySave($permission, $input);

		$phraseInput = $this->filter([
			'title' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($phraseInput)
		{
			if ($phraseInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($phraseInput, $permission)
		{
			$title = $permission->getMasterPhrase();
			$title->phrase_text = $phraseInput['title'];
			$title->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['permission_group_id'] || $params['permission_id'])
		{
			$permission = $this->assertPermissionExists($params['permission_group_id'], $params['permission_id']);
		}
		else
		{
			$permission = $this->em()->create(Permission::class);
		}

		$this->permissionSaveProcess($permission)->run();

		return $this->redirect(
			$this->buildLink('permission-definitions')
			. $this->buildLinkHash("{$permission->permission_group_id}_{$permission->permission_id}")
		);
	}

	public function actionDelete(ParameterBag $params)
	{
		$permission = $this->assertPermissionExists($params->permission_group_id, $params->permission_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$permission,
			$this->buildLink('permission-definitions/permissions/delete', $permission),
			$this->buildLink('permission-definitions/permissions/edit', $permission),
			$this->buildLink('permission-definitions'),
			$permission->title
		);
	}

	protected function interfaceGroupAddEdit(PermissionInterfaceGroup $interfaceGroup)
	{
		$viewParams = [
			'interfaceGroup' => $interfaceGroup,
		];
		return $this->view('XF:Permission\InterfaceGroupEdit', 'permission_interface_group_edit', $viewParams);
	}

	public function actionInterfaceGroupEdit(ParameterBag $params)
	{
		$interfaceGroup = $this->assertInterfaceGroupExists($params['interface_group_id']);
		return $this->interfaceGroupAddEdit($interfaceGroup);
	}

	public function actionInterfaceGroupAdd()
	{
		$interfaceGroup = $this->em()->create(PermissionInterfaceGroup::class);
		return $this->interfaceGroupAddEdit($interfaceGroup);
	}

	protected function interfaceGroupSaveProcess(PermissionInterfaceGroup $interfaceGroup)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'interface_group_id' => 'str',
			'display_order' => 'uint',
			'is_moderator' => 'bool',
			'addon_id' => 'str',
		]);
		$form->basicEntitySave($interfaceGroup, $input);

		$phraseInput = $this->filter([
			'title' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($phraseInput)
		{
			if ($phraseInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($phraseInput, $interfaceGroup)
		{
			$title = $interfaceGroup->getMasterPhrase();
			$title->phrase_text = $phraseInput['title'];
			$title->save();
		});

		return $form;
	}

	public function actionInterfaceGroupSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['interface_group_id'])
		{
			$interfaceGroup = $this->assertInterfaceGroupExists($params['interface_group_id']);
		}
		else
		{
			$interfaceGroup = $this->em()->create(PermissionInterfaceGroup::class);
		}

		$this->interfaceGroupSaveProcess($interfaceGroup)->run();

		return $this->redirect(
			$this->buildLink('permission-definitions') . $this->buildLinkHash("interface_group_{$interfaceGroup->interface_group_id}")
		);
	}

	public function actionInterfaceGroupDelete(ParameterBag $params)
	{
		$interfaceGroup = $this->assertInterfaceGroupExists($params['interface_group_id']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$interfaceGroup,
			$this->buildLink('permission-definitions/interface-groups/delete', $interfaceGroup),
			$this->buildLink('permission-definitions/interface-groups/edit', $interfaceGroup),
			$this->buildLink('permission-definitions'),
			$interfaceGroup->title
		);
	}

	/**
	 * @param string $groupId
	 * @param string $permissionId
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Permission
	 */
	protected function assertPermissionExists($groupId, $permissionId, $with = null, $phraseKey = null)
	{
		$id = ['permission_group_id' => $groupId, 'permission_id' => $permissionId];
		return $this->assertRecordExists(Permission::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return PermissionInterfaceGroup
	 */
	protected function assertInterfaceGroupExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(PermissionInterfaceGroup::class, $id, $with, $phraseKey);
	}

	/**
	 * @return PermissionRepository
	 */
	protected function getPermissionRepo()
	{
		return $this->repository(PermissionRepository::class);
	}
}
