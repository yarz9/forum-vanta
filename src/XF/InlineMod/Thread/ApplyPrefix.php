<?php

namespace XF\InlineMod\Thread;

use XF\Entity\Thread;
use XF\Entity\ThreadPrefix;
use XF\Finder\ThreadPrefixFinder;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Service\Thread\EditorService;

use function count;

/**
 * @extends AbstractAction<Thread>
 */
class ApplyPrefix extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('apply_prefix...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canEdit($error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		if ($entity->discussion_type == 'redirect')
		{
			return;
		}

		if (!$entity->Forum->isPrefixValid($options['prefix_id']))
		{
			return;
		}

		/** @var EditorService $editor */
		$editor = $this->app()->service(EditorService::class, $entity);
		$editor->setPerformValidations(false);
		$editor->setPrefix($options['prefix_id']);
		if ($editor->validate($errors))
		{
			$editor->save();
		}
	}

	public function getBaseOptions()
	{
		return [
			'prefix_id' => null,
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$forums = $entities->pluckNamed('Forum', 'node_id');
		$prefixIds = [];

		foreach ($forums AS $forum)
		{
			$prefixIds = array_merge($prefixIds, array_keys($forum->prefix_cache));
		}

		$prefixes = $this->app()->finder(ThreadPrefixFinder::class)
			->where('prefix_id', array_unique($prefixIds))
			->order('materialized_order')
			->fetch();

		$prefixes = $prefixes->filter(function (ThreadPrefix $prefix)
		{
			return $prefix->isUsableByUser();
		});

		if (!$prefixes->count())
		{
			return $controller->error(\XF::phrase('no_thread_prefixes_available_for_selected_forums'));
		}

		$selectedPrefix = 0;
		$prefixCounts = [0 => 0];
		foreach ($entities AS $thread)
		{
			$threadPrefixId = $thread->prefix_id;

			if (!isset($prefixCounts[$threadPrefixId]))
			{
				$prefixCounts[$threadPrefixId] = 1;
			}
			else
			{
				$prefixCounts[$threadPrefixId]++;
			}

			if ($prefixCounts[$threadPrefixId] > $prefixCounts[$selectedPrefix])
			{
				$selectedPrefix = $threadPrefixId;
			}
		}

		$viewParams = [
			'threads' => $entities,
			'prefixes' => $prefixes->groupBy('prefix_group_id'),
			'forumCount' => count($forums->keys()),
			'selectedPrefix' => $selectedPrefix,
			'total' => count($entities),
		];
		return $controller->view('XF:Public:InlineMod\Thread\ApplyPrefix', 'inline_mod_thread_apply_prefix', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'prefix_id' => $request->filter('prefix_id', 'uint'),
		];
	}
}
