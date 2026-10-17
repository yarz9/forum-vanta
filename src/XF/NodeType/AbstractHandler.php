<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\InputFiltererArray;
use XF\Mvc\FormAction;

abstract class AbstractHandler
{
	protected $nodeTypeId;
	protected $info;

	abstract public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	);

	public function __construct($nodeTypeId, array $info)
	{
		$this->nodeTypeId = $nodeTypeId;
		$this->info = $info;
	}
}
