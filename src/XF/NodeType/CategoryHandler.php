<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\InputFiltererArray;
use XF\Mvc\FormAction;

class CategoryHandler extends AbstractHandler
{
	public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	)
	{
		// don't need to do anything
	}
}
