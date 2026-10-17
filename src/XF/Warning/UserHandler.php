<?php

namespace XF\Warning;

use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<User>
 */
class UserHandler extends AbstractHandler
{
	public function getStoredTitle(Entity $entity)
	{
		return $entity->username;
	}

	public function getDisplayTitle($title)
	{
		return $title;
	}

	public function getContentForConversation(Entity $entity)
	{
		return $entity->username;
	}

	public function getContentUrl(Entity $entity, $canonical = false)
	{
		return \XF::app()->router('public')->buildLink(($canonical ? 'canonical:' : '') . 'members', $entity);
	}

	public function getContentUser(Entity $entity)
	{
		return $entity;
	}

	public function canViewContent(Entity $entity, &$error = null)
	{
		return $entity->canViewFullProfile();
	}

	public function onWarning(Entity $entity, Warning $warning)
	{
	}

	public function onWarningRemoval(Entity $entity, Warning $warning)
	{
	}
}
