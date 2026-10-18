<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\SearchForum
 */
class SearchForum extends AbstractNode
{
	/**
	 * @return string
	 */
	public function getImportType()
	{
		return 'search_forum';
	}

	/**
	 * @return string
	 */
	public function getEntityShortName()
	{
		return 'XF:SearchForum';
	}
}
