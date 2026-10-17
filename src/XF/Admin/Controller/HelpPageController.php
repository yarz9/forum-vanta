<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\HelpPage;
use XF\Finder\HelpPageFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Repository\HelpPageRepository;

class HelpPageController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('help');
	}

	public function actionIndex()
	{
		$pages = $this->getHelpPageRepo()
			->findHelpPagesForList()
			->fetch();

		$viewParams = [
			'pages' => $pages,
		];
		return $this->view('XF:HelpPage\Listing', 'help_page_list', $viewParams);
	}

	protected function pageAddEdit(HelpPage $page)
	{
		$viewParams = [
			'page' => $page,
		];
		return $this->view('XF:HelpPage\Edit', 'help_page_edit', $viewParams);
	}

	public function actionAdd()
	{
		$page = $this->em()->create(HelpPage::class);
		return $this->pageAddEdit($page);
	}

	public function actionEdit(ParameterBag $params)
	{
		$page = $this->assertPageExists($params['page_id']);
		return $this->pageAddEdit($page);
	}

	protected function pageSaveProcess(HelpPage $page)
	{
		$entityInput = $this->filter([
			'page_id' => 'str',
			'page_name' => 'str',
			'display_order' => 'uint',
			'callback_class' => 'str',
			'callback_method' => 'str',
			'advanced_mode' => 'bool',
			'active' => 'bool',
			'addon_id' => 'str',
		]);

		$form = $this->formAction();
		$form->basicEntitySave($page, $entityInput);

		$extraInput = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'content' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($extraInput)
		{
			if ($extraInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($extraInput, $page)
		{
			$title = $page->getMasterPhrase(true);
			$title->phrase_text = $extraInput['title'];
			$title->save();

			$description = $page->getMasterPhrase(false);
			$description->phrase_text = $extraInput['description'];
			$description->save();
		});

		$template = $page->getMasterTemplate();
		$form->validate(function (FormAction $form) use ($extraInput, $template)
		{
			if ($extraInput['content'] === '')
			{
				$form->logError(\XF::phrase('please_enter_page_content'), 'content');
			}
			else if (!$template->set('template', $extraInput['content']))
			{
				$form->logErrors($template->getErrors());
			}
		});
		$form->apply(function () use ($template)
		{
			if ($template)
			{
				$template->save();
			}
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['page_id'])
		{
			$page = $this->assertPageExists($params['page_id']);
		}
		else
		{
			$page = $this->em()->create(HelpPage::class);
		}

		$form = $this->pageSaveProcess($page);
		$form->run();

		return $this->redirect($this->buildLink('help-pages') . $this->buildLinkHash($page->page_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$page = $this->assertPageExists($params['page_id']);
		if (!$page->canEdit())
		{
			return $this->error(\XF::phrase('item_cannot_be_deleted_associated_with_addon_explain'));
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$page,
			$this->buildLink('help-pages/delete', $page),
			$this->buildLink('help-pages/edit', $page),
			$this->buildLink('help-pages'),
			$page->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(HelpPageFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return HelpPage
	 */
	protected function assertPageExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(HelpPage::class, $id, $with, $phraseKey);
	}

	/**
	 * @return HelpPageRepository
	 */
	protected function getHelpPageRepo()
	{
		return $this->repository(HelpPageRepository::class);
	}
}
