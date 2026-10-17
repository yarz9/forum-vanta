<?php

namespace XF\Admin\Controller;

use XF\Entity\Node;
use XF\Mvc\FormAction;

class LinkForumController extends AbstractNode
{
	protected function getNodeTypeId()
	{
		return 'LinkForum';
	}

	protected function getDataParamName()
	{
		return 'link';
	}

	protected function getTemplatePrefix()
	{
		return 'link_forum';
	}

	protected function getViewClassPrefix()
	{
		return 'XF:LinkForum';
	}

	protected function saveTypeData(FormAction $form, Node $node, \XF\Entity\AbstractNode $data)
	{
		$input = $this->filter([
			'link_url' => 'str',
		]);
		$data->bulkSet($input);
	}
}
