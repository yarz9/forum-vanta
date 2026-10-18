<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\UserUpgrade
 */
class UserUpgrade extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'user_upgrade';
	}

	public function getEntityShortName()
	{
		return 'XF:UserUpgrade';
	}
}
