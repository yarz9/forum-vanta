<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\Category
 */
class Category extends AbstractNode
{
	public function getImportType()
	{
		return 'category';
	}

	public function getEntityShortName()
	{
		return 'XF:Category';
	}
}
