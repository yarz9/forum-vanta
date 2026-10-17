<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\InputFiltererArray;
use XF\Mvc\FormAction;
use XF\Util\Arr;

class LinkForumHandler extends AbstractHandler
{
	public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	)
	{
		$typeInput = $inputFilterer->filter([
			'link_url' => '?str',
		]);
		$typeInput = Arr::filterNull($typeInput);
		$data->bulkSet($typeInput);
	}
}
