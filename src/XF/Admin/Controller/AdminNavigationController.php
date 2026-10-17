<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\SortPlugin;
use XF\Entity\AdminNavigation;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\AdminNavigationRepository;
use XF\Repository\AdminPermissionRepository;

use function count;

class AdminNavigationController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertDevelopmentMode();
	}

	public function actionIndex()
	{
		$viewParams = [
			'tree' => $this->getNavigationRepo()->createNavigationTree(),
		];
		return $this->view('XF:AdminNavigation\Listing', 'admin_navigation_list', $viewParams);
	}

	protected function navigationAddEdit(AdminNavigation $navigation)
	{
		/** @var AdminPermissionRepository $adminPermRepo */
		$adminPermRepo = $this->repository(AdminPermissionRepository::class);

		$viewParams = [
			'navigation' => $navigation,
			'navigationTree' => $this->getNavigationRepo()->createNavigationTree(),
			'adminPermissions' => $adminPermRepo->findPermissionsForList()->fetch(),
		];
		return $this->view('XF:AdminNavigation\Edit', 'admin_navigation_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$navigation = $this->assertNavigationExists($params['navigation_id']);
		return $this->navigationAddEdit($navigation);
	}

	public function actionAdd()
	{
		$navigation = $this->em()->create(AdminNavigation::class);

		$navigation->parent_navigation_id = $this->filter('parent_navigation_id', 'str');

		return $this->navigationAddEdit($navigation);
	}

	protected function navigationSaveProcess(AdminNavigation $navigation)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'navigation_id' => 'str',
			'link' => 'str',
			'icon' => 'str',
			'parent_navigation_id' => 'str',
			'display_order' => 'uint',
			'admin_permission_id' => 'str',
			'debug_only' => 'bool',
			'development_only' => 'bool',
			'hide_no_children' => 'bool',
			'addon_id' => 'str',
		]);
		$form->basicEntitySave($navigation, $input);

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
		$form->apply(function () use ($phraseInput, $navigation)
		{
			$title = $navigation->getMasterPhrase();
			$title->phrase_text = $phraseInput['title'];
			$title->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['navigation_id'])
		{
			$navigation = $this->assertNavigationExists($params['navigation_id']);
		}
		else
		{
			$navigation = $this->em()->create(AdminNavigation::class);
		}

		$this->navigationSaveProcess($navigation)->run();

		return $this->redirect($this->buildLink('admin-navigation') . $this->buildLinkHash($navigation->navigation_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$navigation = $this->assertNavigationExists($params['navigation_id']);
		if (!$navigation->preDelete())
		{
			return $this->error($navigation->getErrors());
		}

		if ($this->isPost())
		{
			$navigation->delete();

			return $this->redirect($this->buildLink('admin-navigation'));
		}
		else
		{
			$navRepo = $this->getNavigationRepo();

			$navTree = $navRepo->createNavigationTree();
			$children = $navTree->children($navigation->navigation_id);

			$viewParams = [
				'navigation' => $navigation,
				'hasChildren' => count($children) > 0,
			];
			return $this->view('XF:AdminNavigation\Delete', 'admin_navigation_delete', $viewParams);
		}
	}

	public function actionSort()
	{
		// Note: no need to phrase. This isn't linked anywhere. It's for internal use only.
		// It's dangerous to use unless you go back as it removes custom order changes, so if used,
		// separate manual actions are required.
		return $this->message('This action is disabled.');

		$navTree = $this->getNavigationRepo()->createNavigationTree();

		if ($this->isPost())
		{
			$sorter = $this->plugin(SortPlugin::class);
			$sortTree = $sorter->buildSortTree($this->filter('navigation', 'json-array'), '');
			$sorter->sortTree($sortTree, $navTree->getAllData(), 'parent_navigation_id');

			return $this->redirect($this->buildLink('admin-navigation'));
		}
		else
		{
			$viewParams = [
				'navTree' => $navTree,
			];
			return $this->view('XF:AdminNavigation\Sort', 'admin_navigation_sort', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return AdminNavigation
	 */
	protected function assertNavigationExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(AdminNavigation::class, $id, $with, $phraseKey);
	}

	/**
	 * @return AdminNavigationRepository
	 */
	protected function getNavigationRepo()
	{
		return $this->repository(AdminNavigationRepository::class);
	}
}
