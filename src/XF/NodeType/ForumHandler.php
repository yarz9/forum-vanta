<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Forum;
use XF\Entity\Node;
use XF\InputFiltererArray;
use XF\Mvc\Entity\ArrayValidator;
use XF\Mvc\FormAction;
use XF\Util\Arr;

class ForumHandler extends AbstractHandler
{
	public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	)
	{
		/** @var Forum $data */
		$forumInput = $inputFilterer->filter([
			'allow_posting' => '?bool',
			'moderate_threads' => '?bool',
			'moderate_replies' => '?bool',
			'count_messages' => '?bool',
			'auto_feature' => '?bool',
			'find_new' => '?bool',
			'allowed_watch_notifications' => '?str',
			'default_sort_order' => '?str',
			'default_sort_direction' => '?str',
			'list_date_limit_days' => '?uint',
			'default_prefix_id' => '?uint',
			'require_prefix' => '?bool',
			'min_tags' => '?uint',
			'allow_index' => '?str',
			'index_criteria' => [
				'max_days_post' => '?uint',
				'max_days_last_post' => '?uint',
				'min_replies' => '?uint',
				'min_reaction_score' => '?int',
			],
		]);
		$forumInput = Arr::filterNull($forumInput);

		/** @var Forum $data */
		$data->bulkSet($forumInput);

		if (!$node->exists())
		{
			$forumTypeId = $inputFilterer->filter('forum_type_id', 'str');
			if (!$forumTypeId)
			{
				$forumTypeId = 'discussion';
			}

			$forumTypeHandler = \XF::app()->forumType($forumTypeId, false);
			if ($forumTypeHandler)
			{
				$data->forum_type_id = $forumTypeId;
			}
			else
			{
				$form->logError(\XF::phrase('forum_type_handler_not_found'), 'forum_type_id');
			}
		}
		else
		{
			$forumTypeHandler = $data->getTypeHandler();
		}

		if ($forumTypeHandler)
		{
			$typeConfig = $forumTypeHandler->setupTypeConfigApiSave($form, $node, $data, $inputFilterer);
			if ($typeConfig instanceof ArrayValidator)
			{
				if ($typeConfig->hasErrors())
				{
					$form->logErrors($typeConfig->getErrors());
				}
				$typeConfig = $typeConfig->getValuesForced();
			}
			$data->type_config = $typeConfig;
		}
	}
}
