<?php

namespace XF\Alert;

use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<\XF\Entity\User>
 */
class UserHandler extends AbstractHandler
{
	public function canViewContent(Entity $entity, &$error = null)
	{
		return true;
	}

	public function getOptOutActions()
	{
		return ['following'];
	}

	public function getOptOutDisplayOrder()
	{
		return 30000;
	}
}
