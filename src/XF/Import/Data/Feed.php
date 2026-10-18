<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\Feed
 */
class Feed extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'feed';
	}

	public function getEntityShortName()
	{
		return 'XF:Feed';
	}
}
