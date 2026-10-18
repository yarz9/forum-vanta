<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\Admin
 */
class Admin extends AbstractEntityData
{
	public function getImportType()
	{
		return 'admin';
	}

	public function getEntityShortName()
	{
		return 'XF:Admin';
	}
}
