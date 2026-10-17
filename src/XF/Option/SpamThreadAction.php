<?php

namespace XF\Option;

use XF\Entity\Node;
use XF\Entity\Option;
use XF\Repository\NodeRepository;

class SpamThreadAction extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		/** @var NodeRepository $nodeRepo */
		$nodeRepo = \XF::repository(NodeRepository::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

		return static::getTemplate('admin:option_template_spamThreadAction', $option, $htmlParams, [
			'nodeTree' => $nodeTree,
		]);
	}

	public static function verifyOption(array &$value, Option $option)
	{
		if ($value['action'] == 'move')
		{
			if ($value['node_id'])
			{
				$node = \XF::em()->find(Node::class, $value['node_id']);
				if ($node && $node->node_type_id === 'Forum')
				{
					return true;
				}
			}

			$option->error(\XF::phrase('please_specify_valid_spam_forum'), $option->option_id);
			return false;
		}

		return true;
	}
}
