<?php

namespace XF\Bookmark;

use XF\Entity\Node;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<Node>
 */
class NodeHandler extends AbstractHandler
{
	public function getContentTitle(Entity $content)
	{
		return $content->Data->title;
	}

	public function getContentRoute(Entity $content)
	{
		return $content->getRoute('public');
	}

	public function getCustomIconTemplateName()
	{
		return 'public:bookmark_item_custom_icon_node';
	}
}
