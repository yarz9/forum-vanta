<?php

namespace XF\Admin\Controller;

use XF\Entity\ThreadField;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;
use XF\Repository\ForumFieldRepository;
use XF\Repository\NodeRepository;

class ThreadFieldController extends AbstractField
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('thread');
	}

	protected function getClassIdentifier()
	{
		return 'XF:ThreadField';
	}

	protected function getLinkPrefix()
	{
		return 'custom-thread-fields';
	}

	protected function getTemplatePrefix()
	{
		return 'thread_field';
	}

	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field)
	{
		$reply = parent::fieldAddEditResponse($field);

		if ($reply instanceof View)
		{
			/** @var NodeRepository $nodeRepo */
			$nodeRepo = \XF::repository(NodeRepository::class);
			$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

			// only list nodes that are forums or contain forums
			$nodeTree = $nodeTree->filter(null, function ($id, $node, $depth, $children, $tree)
			{
				return ($children || $node->node_type_id == 'Forum');
			});

			/** @var ArrayCollection $forumFieldAssociations */
			$forumFieldAssociations = $field->getRelationOrDefault('ForumFields', false);

			$reply->setParams([
				'nodeTree' => $nodeTree,
				'nodeIds' => $forumFieldAssociations->pluckNamed('node_id'),
			]);
		}

		return $reply;
	}

	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractField $field)
	{
		$nodeIds = $this->filter('node_ids', 'array-uint');

		/** @var ThreadField $field */
		$form->complete(function () use ($field, $nodeIds)
		{
			/** @var ForumFieldRepository $repo */
			$repo = $this->repository(ForumFieldRepository::class);
			$repo->updateFieldAssociations($field, $nodeIds);
		});

		return $form;
	}
}
