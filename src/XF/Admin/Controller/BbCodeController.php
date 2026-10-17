<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\ControllerPlugin\XmlPlugin;
use XF\Entity\BbCode;
use XF\Finder\BbCodeFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Repository\BbCodeRepository;

class BbCodeController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	public function actionIndex()
	{
		$bbCodes = $this->getBbCodeRepo()
			->findBbCodesForList();

		$viewParams = [
			'bbCodes' => $bbCodes->fetch(),
			'exportView' => $this->filter('export', 'bool'),
		];
		return $this->view('XF:BbCode\Listing', 'bb_code_list', $viewParams);
	}

	public function bbCodeAddEdit(BbCode $bbCode)
	{
		$viewParams = [
			'bbCode' => $bbCode,
		];
		return $this->view('XF:BbCode\Edit', 'bb_code_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$bbCode = $this->assertBbCodeExists($params['bb_code_id']);
		return $this->bbCodeAddEdit($bbCode);
	}

	public function actionAdd()
	{
		$bbCode = $this->em()->create(BbCode::class);
		return $this->bbCodeAddEdit($bbCode);
	}

	protected function bbCodeSaveProcess(BbCode $bbCode)
	{
		$entityInput = $this->filter([
			'bb_code_id' => 'str',
			'bb_code_mode' => 'str',
			'has_option' => 'str',
			'replace_html' => 'str,no-trim',
			'replace_html_email' => 'str,no-trim',
			'replace_text' => 'str,no-trim',
			'callback_class' => 'str',
			'callback_method' => 'str',
			'option_regex' => 'str',
			'trim_lines_after' => 'uint',
			'plain_children' => 'bool',
			'disable_smilies' => 'bool',
			'disable_nl2br' => 'bool',
			'disable_autolink' => 'bool',
			'allow_empty' => 'bool',
			'allow_signature' => 'bool',
			'editor_icon_type' => 'str',
			'active' => 'bool',
			'addon_id' => 'str',
		]);

		if ($entityInput['editor_icon_type'] == 'fa')
		{
			$entityInput['editor_icon_value'] = $this->filter('editor_icon_fa', 'str');
		}
		else if ($entityInput['editor_icon_type'] == 'image')
		{
			$entityInput['editor_icon_value'] = $this->filter('editor_icon_image', 'str');
		}
		else
		{
			$entityInput['editor_icon_value'] = '';
		}

		$form = $this->formAction();
		$form->basicEntitySave($bbCode, $entityInput);

		$phraseInput = $this->filter([
			'title' => 'str',
			'desc' => 'str',
			'example' => 'str',
			'output' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($phraseInput)
		{
			if ($phraseInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($phraseInput, $bbCode)
		{
			foreach ($phraseInput AS $type => $text)
			{
				$masterPhrase = $bbCode->getMasterPhrase($type);
				$masterPhrase->phrase_text = $text;
				$masterPhrase->save();
			}
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['bb_code_id'])
		{
			$bbCode = $this->assertBbCodeExists($params['bb_code_id']);
		}
		else
		{
			$bbCode = $this->em()->create(BbCode::class);
		}

		$this->bbCodeSaveProcess($bbCode)->run();

		return $this->redirect($this->buildLink('bb-codes'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$bbCode = $this->assertBbCodeExists($params['bb_code_id']);
		if (!$bbCode->canEdit())
		{
			return $this->error(\XF::phrase('item_cannot_be_deleted_associated_with_addon_explain'));
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$bbCode,
			$this->buildLink('bb-codes/delete', $bbCode),
			$this->buildLink('bb-codes/edit', $bbCode),
			$this->buildLink('bb-codes'),
			$bbCode->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(BbCodeFinder::class);
	}

	public function actionExport()
	{
		$bbCodes = $this->finder(BbCodeFinder::class)->order('bb_code_id')
			->where('bb_code_id', $this->filter('export', 'array-str'));

		return $this->plugin(XmlPlugin::class)->actionExport($bbCodes, 'XF:BbCode\Export');
	}

	public function actionImport()
	{
		return $this->plugin(XmlPlugin::class)->actionImport('bb-codes', 'bb_codes', 'XF:BbCode\Import');
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return BbCode
	 */
	protected function assertBbCodeExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(BbCode::class, $id, $with, $phraseKey);
	}

	/**
	 * @return BbCodeRepository
	 */
	protected function getBbCodeRepo()
	{
		return $this->repository(BbCodeRepository::class);
	}
}
