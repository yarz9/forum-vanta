<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\BbCodePreviewPlugin;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\DraftPlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Data\Editor;
use XF\Entity\EditorDropdown;
use XF\Finder\EditorDropdownFinder;
use XF\Mvc\Entity\Repository;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;
use XF\Repository\BbCodeRepository;
use XF\Repository\EditorRepository;
use XF\Repository\OptionRepository;

use function array_key_exists, in_array, is_array, strlen;

class ButtonManagerController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	public function actionIndex()
	{
		$editorRepo = $this->getEditorRepo();
		$toolbarTypes = $editorRepo->getToolbarTypes();
		$editorDropdowns = $editorRepo->findEditorDropdownsForList()->fetch();

		$viewParams = [
			'toolbarTypes' => $toolbarTypes,
			'editorDropdowns' => $editorDropdowns,
		];
		return $this->view('XF:ButtonManager\List', 'bb_code_button_manager_list', $viewParams);
	}

	public function actionEdit()
	{
		$editorRepo = $this->getEditorRepo();
		$toolbarTypes = $editorRepo->getToolbarTypes();
		$toolbarSizes = $editorRepo->getToolbarSizes();

		$type = $this->filter('type', 'str');

		if (!$type || !isset($toolbarTypes[$type]))
		{
			return $this->notFound();
		}

		$toolbarButtons = $this->options()->editorToolbarConfig[$type];
		if (!$this->validateToolbarButtons($toolbarButtons))
		{
			$toolbarButtons = $this->getEditorRepo()->getDefaultToolbarConfig($type);
		}

		$availableButtons = $this->getAvailableButtons();
		$this->removeOrphanedButtons($toolbarButtons, $availableButtons);

		$viewParams = [
			'buttonData' => $availableButtons,
			'toolbarButtons' => ['toolbarButtons' => $toolbarButtons],
			'type' => $type,
			'typeTitle' => $toolbarTypes[$type]['title'],
			'typeDescription' => $toolbarTypes[$type]['description'],
			'toolbarTypes' => $toolbarTypes,
			'toolbarSizes' => $toolbarSizes,
		];
		return $this->view('XF:ButtonManager\Editor', 'bb_code_button_manager_editor', $viewParams);
	}

	protected function getAvailableButtons(): array
	{
		$customBbCodes = $this->repository(BbCodeRepository::class)->findBbCodesForList()
			->where('editor_icon_type', '<>', '')
			->fetch();

		$dropdowns = $this->getEditorRepo()->findEditorDropdownsForList()->fetch();

		/** @var Editor $data */
		$data = $this->data(Editor::class);
		return $data->getCombinedButtonData($customBbCodes, $dropdowns);
	}

	protected function validateToolbarButtons($config): bool
	{
		if (!is_array($config))
		{
			return false;
		}

		foreach ($config AS $key => $buttonGroup)
		{
			if (!isset($buttonGroup['buttons']))
			{
				return false;
			}
		}

		return true;
	}

	protected function removeOrphanedButtons(array &$toolbarButtons, array $availableButtons)
	{
		foreach ($toolbarButtons AS $groupId => $group)
		{
			foreach ($group['buttons'] AS $index => $button)
			{
				if (!array_key_exists($button, $availableButtons))
				{
					unset($toolbarButtons[$groupId]['buttons'][$index]);
				}
			}
		}
	}

	public function actionSave()
	{
		$this->assertPostOnly();

		$editorRepo = $this->getEditorRepo();
		$toolbarTypes = $editorRepo->getToolbarTypes();

		$type = $this->filter('type', 'str');

		if (!$type || !isset($toolbarTypes[$type]))
		{
			return $this->notFound();
		}

		$buttonConfig = $this->options()->editorToolbarConfig;
		$typeConfig = $this->filter('editor_toolbar_config.' . $type, 'json-array');
		$buttonConfig[$type] = $typeConfig;

		/** @var OptionRepository $optionRepo */
		$optionRepo = $this->repository(OptionRepository::class);
		$optionRepo->updateOption('editorToolbarConfig', $buttonConfig);

		return $this->redirect($this->buildLink('button-manager'));
	}

	public function actionReset()
	{
		$editorRepo = $this->getEditorRepo();
		$toolbarTypes = $editorRepo->getToolbarTypes();

		$type = $this->filter('type', 'str');

		if (!$type || !isset($toolbarTypes[$type]))
		{
			return $this->notFound();
		}

		$toolbarType = $toolbarTypes[$type];

		if ($this->isPost())
		{
			$buttonConfig = $this->options()->editorToolbarConfig;
			$buttonConfig[$type] = $this->getEditorRepo()->getDefaultToolbarConfig($type);

			/** @var OptionRepository $optionRepo */
			$optionRepo = $this->repository(OptionRepository::class);
			$optionRepo->updateOption('editorToolbarConfig', $buttonConfig);

			return $this->redirect($this->buildLink('button-manager/edit', null, ['type' => $type]));
		}
		else
		{
			$viewParams = [
				'type' => $type,
				'typeTitle' => $toolbarType['title'],
				'typeDescription' => $toolbarType['description'],
			];
			return $this->view('XF:ButtonManager\Reset', 'bb_code_button_manager_reset', $viewParams);
		}
	}

	public function dropdownAddEdit(EditorDropdown $dropdown)
	{
		// separators (if used) are unsupported inside dropdowns
		$invalidCommands = [
			'-vs',
			'-hs',
		];

		$buttonData = array_filter(
			$this->getAvailableButtons(),
			function ($data, $key) use ($invalidCommands)
			{
				if (in_array($key, $invalidCommands))
				{
					return false;
				}

				if (isset($data['type']) && ($data['type'] == 'dropdown' || $data['type'] == 'editable_dropdown'))
				{
					// nested dropdowns not supported
					return false;
				}

				return true;
			},
			ARRAY_FILTER_USE_BOTH
		);

		$viewParams = [
			'dropdown' => $dropdown,
			'buttonData' => $buttonData,
		];
		return $this->view('XF:ButtonManager\Dropdown\Edit', 'bb_code_button_manager_dropdown_edit', $viewParams);
	}

	public function actionDropdownEdit(ParameterBag $params)
	{
		$dropdown = $this->assertRecordExists(EditorDropdown::class, $params->cmd);
		return $this->dropdownAddEdit($dropdown);
	}

	public function actionDropdownAdd()
	{
		$dropdown = $this->em()->create(EditorDropdown::class);
		return $this->dropdownAddEdit($dropdown);
	}

	protected function dropdownSaveProcess(EditorDropdown $dropdown)
	{
		$form = $this->formAction();

		$dropdownInput = $this->filter([
			'icon' => 'str',
			'display_order' => 'uint',
			'active' => 'bool',
		]);

		if ($dropdown->isInsert())
		{
			$dropdownInput['cmd'] = $this->filter('cmd', 'str');
		}

		$buttons = $this->filter('buttons', 'json-array');
		$dropdownInput['buttons'] = $buttons['']['buttons'] ?? [];

		$form->basicEntitySave($dropdown, $dropdownInput);

		$title = $this->filter('title', 'str');
		$form->validate(function (FormAction $form) use ($title)
		{
			if ($title === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($title, $dropdown)
		{
			$masterTitle = $dropdown->getMasterPhrase();
			$masterTitle->phrase_text = $title;
			$masterTitle->save();
		});

		return $form;
	}

	public function actionDropdownSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->cmd)
		{
			$dropdown = $this->assertRecordExists(EditorDropdown::class, $params->cmd);
		}
		else
		{
			$dropdown = $this->em()->create(EditorDropdown::class);
		}

		$this->dropdownSaveProcess($dropdown)->run();

		return $this->redirect($this->buildLink('button-manager'));
	}

	public function actionDropdownDelete(ParameterBag $params)
	{
		$dropdown = $this->assertRecordExists(EditorDropdown::class, $params->cmd);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$dropdown,
			$this->buildLink('button-manager/dropdown/delete', $dropdown),
			$this->buildLink('button-manager/dropdown/edit', $dropdown),
			$this->buildLink('button-manager'),
			$dropdown->title
		);
	}

	public function actionDropdownToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(EditorDropdownFinder::class);
	}

	/**
	 * @return Repository|EditorRepository
	 */
	protected function getEditorRepo()
	{
		return $this->repository(EditorRepository::class);
	}

	// Dummy actions for the RTE preview in the button manager
	/**
	 * This doesn't do anything except return the expected data for a saved or deleted draft
	 *
	 * @return View
	 */
	public function actionDummyDraft()
	{
		$view = $this->view('XF:ButtonManager\DummyDraft', '', []);
		$view->setJsonParam('hasNew', false);

		/** @var DraftPlugin $draftPlugin */
		$draftPlugin = $this->plugin(DraftPlugin::class);
		$draftPlugin->addDraftJsonParams($view, $this->request->filter('delete', 'bool') ? 'delete' : 'save');

		return $view;
	}

	public function actionDummyPreview()
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('button_layout_preview');
		if (!strlen($message))
		{
			$message = \XF::phrase('preview');
		}

		$view = $this->plugin(BbCodePreviewPlugin::class)->actionPreview($message, 'post', \XF::visitor());
		$view->setTemplateName('public:' . $view->getTemplateName());

		return $view;
	}

	public function actionDummyPost()
	{
		return $this->view('XF:ButtonManager\DummyPost', '', []);
	}
}
