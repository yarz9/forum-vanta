<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\BookmarkItem
 */
class BookmarkItem extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'bookmark';
	}

	protected function getEntityShortName()
	{
		return 'XF:BookmarkItem';
	}
}
