<?php

namespace XF\InlineMod\Post;

use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\NodeRepository;
use XF\Service\Post\CopierService;

use function count;

class Copy extends AbstractMoveCopy
{
	public function getTitle()
	{
		return \XF::phrase('copy_posts...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canCopy($error);
	}

	public function applyInternal(AbstractCollection $entities, array $options)
	{
		$thread = $this->getTargetThreadFromOptions($options);

		/** @var CopierService $copier */
		$copier = $this->app()->service(CopierService::class, $thread);
		$copier->setExistingTarget($options['thread_type'] == 'existing' ? true : false);

		if ($options['alert'])
		{
			$copier->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['prefix_id'] !== null && $options['thread_type'] !== 'existing')
		{
			$copier->setPrefix($options['prefix_id']);
		}

		$copier->copy($entities);

		$this->returnUrl = $this->app()->router('public')->buildLink('threads', $copier->getTarget());
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		/** @var NodeRepository $nodeRepo */
		$nodeRepo = $this->app()->repository(NodeRepository::class);
		$nodes = $nodeRepo->getFullNodeList()->filterViewable();

		$viewParams = [
			'posts' => $entities,
			'total' => count($entities),
			'nodeTree' => $nodeRepo->createNodeTree($nodes),
			'first' => $entities->first(),
			'prefixes' => $entities->first()->Thread->Forum->getUsablePrefixes(),
		];
		return $controller->view('XF:Public:InlineMod\Post\Copy', 'inline_mod_post_copy', $viewParams);
	}
}
