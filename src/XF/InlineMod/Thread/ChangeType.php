<?php

namespace XF\InlineMod\Thread;

use XF\Entity\Thread;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\ThreadTypeRepository;
use XF\Service\Thread\ChangeTypeService;

use function count;

/**
 * @extends AbstractAction<Thread>
 */
class ChangeType extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('change_thread_type...');
	}

	protected function canApplyInternal(AbstractCollection $entities, array $options, &$error)
	{
		$result = parent::canApplyInternal($entities, $options, $error);
		if (!$result)
		{
			return $result;
		}

		if ($options['new_thread_type_id'] !== null)
		{
			$isValidThreadType = (
				$options['new_thread_type_id']
				&& $this->app()->threadType($options['new_thread_type_id'], false)
			);
			if (!$isValidThreadType)
			{
				$error = \XF::phrase('please_select_valid_thread_type');
				return false;
			}
		}

		return true;
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canChangeType($error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		$newThreadType = $this->app()->threadType($options['new_thread_type_id'], false);
		if (!$newThreadType || !$newThreadType->canConvertThreadToType(true))
		{
			return;
		}

		$forum = $entity->Forum;
		if (!$forum->TypeHandler->isThreadTypeAllowed($options['new_thread_type_id'], $forum))
		{
			return;
		}

		/** @var ChangeTypeService $typeChanger */
		$typeChanger = $this->app()->service(ChangeTypeService::class, $entity);
		$typeChanger->setDiscussionTypeForBulkChange($newThreadType->getTypeId());
		if ($typeChanger->validate($errors))
		{
			$typeChanger->save();
		}
	}

	public function getBaseOptions()
	{
		return [
			'new_thread_type_id' => null,
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$forums = $entities->pluckNamed('Forum', 'node_id');
		$availableTypes = [];

		foreach ($forums AS $forum)
		{
			$availableTypes = array_merge($availableTypes, $forum->getCreatableThreadTypes());
		}

		$creatableThreadTypes = $this->app()->repository(ThreadTypeRepository::class)->getThreadTypeListData(
			$availableTypes,
			ThreadTypeRepository::FILTER_BULK_CONVERTIBLE
		);

		if (count($creatableThreadTypes) <= 1)
		{
			return $controller->error(\XF::phrase('no_other_thread_types_for_selected_cannot_change'));
		}

		$viewParams = [
			'threads' => $entities,
			'total' => count($entities),
			'creatableThreadTypes' => $creatableThreadTypes,
		];
		return $controller->view('XF:Public:InlineMod\Thread\ChangeType', 'inline_mod_thread_change_type', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'new_thread_type_id' => $request->filter('new_thread_type_id', 'str'),
		];
	}
}
