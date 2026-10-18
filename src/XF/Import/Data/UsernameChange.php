<?php

namespace XF\Import\Data;

use XF\Entity\User;
use XF\Repository\UsernameChangeRepository;

/**
 * @mixin \XF\Entity\UsernameChange
 */
class UsernameChange extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'username_change';
	}

	public function getEntityShortName()
	{
		return 'XF:UsernameChange';
	}

	protected function postSave($oldId, $newId)
	{
		if ($this->change_state == 'moderated')
		{
			$this->db()->insert('xf_approval_queue', [
				'content_type' => 'username_change',
				'content_id' => $this->change_id,
				'content_date' => $this->change_date,
			], false, 'content_date = VALUES(content_date)');
		}

		if ($this->change_state == 'approved' && $this->visible)
		{
			/** @var User $user */
			$user = $this->em()->find(User::class, $this->user_id);
			if ($user)
			{
				$this->repository(UsernameChangeRepository::class)->rebuildLastVisibleUsernameChange($user);

				$this->em()->detachEntity($user);
			}
		}
	}
}
