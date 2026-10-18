<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\LinkForum
 */
class LinkForum extends AbstractNode
{
	public function getImportType()
	{
		return 'link_forum';
	}

	public function getEntityShortName()
	{
		return 'XF:LinkForum';
	}
}
