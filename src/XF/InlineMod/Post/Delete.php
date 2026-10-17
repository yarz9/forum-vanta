<?php

namespace XF\InlineMod\Post;

use XF\Entity\Post;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Service\Post\DeleterService;

use function count;

/**
 * @extends AbstractAction<Post>
 */
class Delete extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('delete_posts...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canDelete($options['type'], $error);
	}

	protected function applyInternal(AbstractCollection $entities, array $options)
	{
		$skipped = [];

		foreach ($entities AS $entity)
		{
			if (!empty($skipped[$entity->thread_id]))
			{
				continue;
			}

			$this->applyToEntity($entity, $options);

			$thread = $entity->Thread;
			if ($options['type'] == 'hard' && $entity->post_id == $thread->first_post_id)
			{
				// all posts in this thread have been removed, so skip them
				$skipped[$thread->thread_id] = true;
			}
		}
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var DeleterService $deleter */
		$deleter = $this->app()->service(DeleterService::class, $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);

		if ($deleter->wasThreadDeleted())
		{
			$this->returnUrl = $this->app()->router('public')->buildLink('forums', $entity->Thread->Forum);
		}
	}

	public function getBaseOptions()
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'alert' => false,
			'alert_reason' => '',
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$firstPostCount = 0;
		foreach ($entities AS $post)
		{
			if ($post->post_id == $post->Thread->first_post_id)
			{
				$firstPostCount++;
			}
		}

		$viewParams = [
			'posts' => $entities,
			'firstPostCount' => $firstPostCount,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
		];
		return $controller->view('XF:Public:InlineMod\Post\Delete', 'inline_mod_post_delete', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
	}
}
