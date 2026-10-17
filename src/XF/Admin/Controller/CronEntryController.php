<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\CronEntry;
use XF\Finder\CronEntryFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Repository\CronEntryRepository;

use function call_user_func;

class CronEntryController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('cron');
	}

	public function actionIndex()
	{
		$viewParams = [
			'entries' => $this->getCronRepo()
				->findCronEntriesForList()
				->fetch(),
		];
		return $this->view('XF:CronEntry\Listing', 'cron_list', $viewParams);
	}

	protected function cronAddEdit(CronEntry $entry)
	{
		$viewParams = [
			'entry' => $entry,
		];
		return $this->view('XF:CronEntry\Edit', 'cron_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$entry = $this->assertCronEntryExists($params->entry_id);
		return $this->cronAddEdit($entry);
	}

	public function actionAdd()
	{
		$entry = $this->em()->create(CronEntry::class);
		return $this->cronAddEdit($entry);
	}

	protected function cronSaveProcess(CronEntry $entry)
	{
		$input = $this->filter([
			'entry_id' => 'str',
			'cron_class' => 'str',
			'cron_method' => 'str',
			'run_rules' => 'array',
			'active' => 'bool',
			'addon_id' => 'str',
		]);

		$form = $this->formAction();
		$form->basicEntitySave($entry, $input);

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
		$form->apply(function () use ($phraseInput, $entry)
		{
			$title = $entry->getMasterPhrase();
			$title->phrase_text = $phraseInput['title'];
			$title->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->entry_id)
		{
			$entry = $this->assertCronEntryExists($params->entry_id);
		}
		else
		{
			$entry = $this->em()->create(CronEntry::class);
		}

		$this->cronSaveProcess($entry)->run();

		return $this->redirect($this->buildLink('cron') . $this->buildLinkHash($entry->entry_id));
	}

	public function actionRun(ParameterBag $params)
	{
		$entry = $this->assertCronEntryExists($params->entry_id);

		if ($entry->addon_id && !$entry->AddOn->active)
		{
			return $this->error(\XF::phrase('cron_entry_associated_with_inactive_add_on_cannot_run'));
		}

		if ($this->isPost())
		{
			if ($entry->hasCallback())
			{
				call_user_func(
					[$entry->cron_class, $entry->cron_method],
					$entry->toArray()
				);
			}
			return $this->message(\XF::phrase('cron_entry_run_successfully'));
		}
		else
		{
			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:CronEntry\Run', 'cron_run', $viewParams);
		}
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(CronEntryFinder::class);
	}

	public function actionDelete(ParameterBag $params)
	{
		$entry = $this->assertCronEntryExists($params->entry_id);
		if (!$entry->canEdit())
		{
			return $this->error(\XF::phrase('item_cannot_be_deleted_associated_with_addon_explain'));
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$entry,
			$this->buildLink('cron/delete', $entry),
			$this->buildLink('cron/edit', $entry),
			$this->buildLink('cron'),
			$entry->title
		);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return CronEntry
	 */
	protected function assertCronEntryExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(CronEntry::class, $id, $with, $phraseKey);
	}

	/**
	 * @return CronEntryRepository
	 */
	protected function getCronRepo()
	{
		return $this->repository(CronEntryRepository::class);
	}
}
