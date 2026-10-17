<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\Entity\ActivitySummaryDefinition;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\ActivitySummaryRepository;

class ActivitySummaryDefinitionController extends AbstractController
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
		$activitySummaryRepo = $this->getActivitySummaryRepo();
		$definitionsFinder = $activitySummaryRepo->findActivitySummaryDefinitionsForList();

		$viewParams = [
			'definitions' => $definitionsFinder->fetch(),
		];
		return $this->view('XF:ActivitySummary\Definition\Listing', 'activity_summary_definition_list', $viewParams);
	}

	protected function definitionAddEdit(ActivitySummaryDefinition $definition)
	{
		$viewParams = [
			'definition' => $definition,
		];
		return $this->view('XF:ActivitySummary\Definition\Edit', 'activity_summary_definition_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$definition = $this->assertDefinitionExists($params->definition_id);
		return $this->definitionAddEdit($definition);
	}

	public function actionAdd()
	{
		$definition = $this->em()->create(ActivitySummaryDefinition::class);
		return $this->definitionAddEdit($definition);
	}

	protected function definitionSaveProcess(ActivitySummaryDefinition $definition)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'definition_id' => 'str',
			'definition_class' => 'str',
			'addon_id' => 'str',
		]);

		$form->basicEntitySave($definition, $input);

		$extraInput = $this->filter([
			'title' => 'str',
			'description' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($extraInput)
		{
			if ($extraInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function (FormAction $form) use ($extraInput, $definition)
		{
			$title = $definition->getMasterTitlePhrase();
			$title->phrase_text = $extraInput['title'];
			$title->save();

			$description = $definition->getMasterDescriptionPhrase();
			$description->phrase_text = $extraInput['description'];
			$description->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->definition_id)
		{
			$definition = $this->assertDefinitionExists($params->definition_id);
		}
		else
		{
			$definition = $this->em()->create(ActivitySummaryDefinition::class);
		}

		$this->definitionSaveProcess($definition)->run();

		return $this->redirect($this->buildLink('activity-summary/definitions') . $this->buildLinkHash($definition->definition_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$definition = $this->assertDefinitionExists($params->definition_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$definition,
			$this->buildLink('activity-summary/definitions/delete', $definition),
			$this->buildLink('activity-summary/definitions/edit', $definition),
			$this->buildLink('activity-summary/definitions'),
			$definition->title
		);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ActivitySummaryDefinition
	 */
	protected function assertDefinitionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ActivitySummaryDefinition::class, $id, $with, $phraseKey);
	}

	/**
	 * @return ActivitySummaryRepository
	 */
	protected function getActivitySummaryRepo()
	{
		return $this->repository(ActivitySummaryRepository::class);
	}
}
