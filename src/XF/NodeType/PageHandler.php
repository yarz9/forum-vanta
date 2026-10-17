<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\Entity\Page;
use XF\InputFiltererArray;
use XF\Mvc\FormAction;
use XF\Util\Arr;

class PageHandler extends AbstractHandler
{
	public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	)
	{
		$typeInput = $inputFilterer->filter([
			'log_visits' => '?bool',
			'list_siblings' => '?bool',
			'list_children' => '?bool',
			'advanced_mode' => '?bool',
		]);
		$typeInput = Arr::filterNull($typeInput);

		/** @var Page $data */
		$data->bulkSet($typeInput);
		$data->modified_date = \XF::$time;

		$templateInput = $inputFilterer->filter('template', '?str');
		if ($templateInput !== null)
		{
			$template = $data->getMasterTemplate();
			$template->template = $templateInput;
			$data->addCascadedSave($template);
		}
	}
}
