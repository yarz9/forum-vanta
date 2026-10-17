<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\Entity\ContentTypeField;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\ContentTypeFieldRepository;

class ContentTypeController extends AbstractController
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
		/** @var ContentTypeFieldRepository $fieldRepo */
		$fieldRepo = $this->repository(ContentTypeFieldRepository::class);

		$fields = $fieldRepo->findContentTypeFieldsForList()->fetch();

		$fieldsGrouped = $fields->groupBy('content_type');

		$typesGrouped = $fields->groupBy('field_name');
		ksort($typesGrouped);

		$viewParams = [
			'fieldsGrouped' => $fieldsGrouped,
			'typesGrouped' => $typesGrouped,
			'group' => $this->filter('group', 'string'),
		];
		return $this->view('XF:ContentType\Listing', 'content_type_list', $viewParams);
	}

	protected function fieldAddEdit(ContentTypeField $field, $group = '')
	{
		$viewParams = [
			'field' => $field,
			'group' => $group,
		];
		return $this->view('XF:ContentType\Edit', 'content_type_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$field = $this->assertFieldExists($params['content_type'], $params['field_name']);
		return $this->fieldAddEdit($field, $this->filter('group', 'string'));
	}

	public function actionAdd()
	{
		$field = $this->em()->create(ContentTypeField::class);

		if ($contentType = $this->filter('content_type', 'string'))
		{
			$field->content_type = $contentType;
			return $this->fieldAddEdit($field, 'content_type');
		}

		if ($fieldName = $this->filter('field_name', 'string'))
		{
			$field->field_name = $fieldName;
			return $this->fieldAddEdit($field, 'field_name');
		}

		return $this->fieldAddEdit($field);
	}

	protected function fieldSaveProcess(ContentTypeField $field)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'content_type' => 'str',
			'field_name' => 'str',
			'field_value' => 'str',
			'addon_id' => 'str',
		]);

		$form->basicEntitySave($field, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['content_type'] && $params['field_name'])
		{
			$field = $this->assertFieldExists($params['content_type'], $params['field_name']);
		}
		else
		{
			$field = $this->em()->create(ContentTypeField::class);
		}

		$this->fieldSaveProcess($field)->run();

		$group = $this->filter('group', 'string');
		if ($group == 'field_name')
		{
			$linkParams = ['group' => $group];
		}
		else
		{
			$linkParams = [];
		}

		return $this->redirect(
			$this->buildLink('content-types', null, $linkParams)
			. $this->buildLinkHash($field->content_type . '_' . $field->field_name)
		);
	}

	public function actionDelete(ParameterBag $params)
	{
		$field = $this->assertFieldExists($params['content_type'], $params['field_name']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$field,
			$this->buildLink('content-types/delete', $field),
			$this->buildLink('content-types/edit', $field),
			$this->buildLink('content-types'),
			"{$field->content_type} - {$field->field_name}"
		);
	}

	/**
	 * @param string $contentType
	 * @param string $fieldName
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ContentTypeField
	 */
	protected function assertFieldExists($contentType, $fieldName, $with = null, $phraseKey = null)
	{
		$id = ['content_type' => $contentType, 'field_name' => $fieldName];
		return $this->assertRecordExists(ContentTypeField::class, $id, $with, $phraseKey);
	}
}
