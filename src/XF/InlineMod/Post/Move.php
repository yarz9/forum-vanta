<?php

namespace XF\InlineMod\Post;

use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XF\Repository\NodeRepository;
use XF\Service\Post\MoverService;

use function count;

class Move extends AbstractMoveCopy
{
	public function getTitle()
	{
		return \XF::phrase('move_posts...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canMove($error);
	}

	public function applyInternal(AbstractCollection $entities, array $options)
	{
		$thread = $this->getTargetThreadFromOptions($options);

		/** @var MoverService $mover */
		$mover = $this->app()->service(MoverService::class, $thread);
		$mover->setExistingTarget($options['thread_type'] == 'existing' ? true : false);

		if ($options['alert'])
		{
			$mover->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['prefix_id'] !== null && $options['thread_type'] !== 'existing')
		{
			$mover->setPrefix($options['prefix_id']);
		}

		if (!$mover->move($entities))
		{
			throw new PrintableException(\XF::phrase('it_is_not_possible_to_move_any_of_selected_posts_to_specified_target'));
		}

		$this->returnUrl = $this->app()->router('public')->buildLink('threads', $mover->getTarget());
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
		return $controller->view('XF:Public:InlineMod\Post\Move', 'inline_mod_post_move', $viewParams);
	}
}
