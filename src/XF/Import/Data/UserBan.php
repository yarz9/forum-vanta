<?php

namespace XF\Import\Data;

use XF\Behavior\ChangeLoggable;
use XF\Entity\User;

/**
 * @mixin \XF\Entity\UserBan
 */
class UserBan extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'user_ban';
	}

	public function getEntityShortName()
	{
		return 'XF:UserBan';
	}

	protected function postSave($oldId, $newId)
	{
		/** @var User $user */
		$user = $this->em()->find(User::class, $this->user_id);
		if ($user)
		{
			$user->is_banned = true;
			$user->getBehavior(ChangeLoggable::class)->setOption('enabled', false);
			$user->saveIfChanged($saved, false, false);
		}
	}
}
