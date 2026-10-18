<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\EditHistory
 */
class EditHistory extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'edit_history';
	}

	public function getEntityShortName()
	{
		return 'XF:EditHistory';
	}
}
