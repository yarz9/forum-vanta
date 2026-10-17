<?php

namespace XF\Repository;

use XF\Entity\User;
use XF\Entity\UsernameChange;
use XF\Finder\UsernameChangeFinder;
use XF\Mvc\Entity\Repository;
use XF\Util\Str;

class UsernameChangeRepository extends Repository
{
	public function findUsernameChangesForList(): UsernameChangeFinder
	{
		return $this->finder(UsernameChangeFinder::class)
			->with('User', true)
			->setDefaultOrder('change_date', 'DESC');
	}

	public function findUsernameChangeHistoryForUser($userId): UsernameChangeFinder
	{
		return $this->finder(UsernameChangeFinder::class)
			->where('user_id', $userId)
			->where('change_state', 'approved')
			->setDefaultOrder('change_date', 'DESC');
	}

	public function findChangesFromUsername($username): UsernameChangeFinder
	{
		return $this->finder(UsernameChangeFinder::class)
			->where('old_username', $username)
			->where('change_state', 'approved')
			->setDefaultOrder('change_date', 'DESC');
	}

	public function findPendingUsernameChanges(): UsernameChangeFinder
	{
		return $this->finder(UsernameChangeFinder::class)
			->where('change_state', 'moderated')
			->setDefaultOrder('change_date', 'DESC');
	}

	/**
	 * Insert a username change log. This method is for inserting a record of a change that has already happened.
	 * The new username will not be validated to determine if it's valid for someone to change to it.
	 *
	 * @param int $userId
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @param bool $visible
	 * @param null|int $changeUserId
	 * @param null|int $changeDate
	 *
	 * @return UsernameChange
	 */
	public function insertUsernameChangeLog(
		$userId,
		$oldUsername,
		$newUsername,
		$visible = true,
		$changeUserId = null,
		$changeDate = null
	): UsernameChange
	{
		if ($changeUserId === null)
		{
			$changeUserId = \XF::visitor()->user_id;
		}

		/** @var UsernameChange $entry */
		$entry = $this->em->create(UsernameChange::class);

		$entry->bulkSet([
			'user_id' => $userId,
			'old_username' => $oldUsername,
			'change_state' => 'approved',
			'change_user_id' => $changeUserId,
			'visible' => $visible,
		]);

		// this method is for logging changes that are happening, not for validating the change itself,
		// so we need to force this through.
		$entry->setTrusted('new_username', $newUsername);

		if ($changeDate !== null)
		{
			$entry->change_date = $changeDate;
		}

		$entry->save();

		return $entry;
	}

	public function clearPendingUsernameChanges(
		User $user,
		?UsernameChange $skipChange = null,
		?User $moderator = null
	)
	{
		if ($moderator === null)
		{
			$moderator = \XF::visitor();
		}

		$pendingChanges = $this->finder(UsernameChangeFinder::class)->where([
			'user_id' => $user->user_id,
			'change_state' => 'moderated',
		]);
		if ($skipChange)
		{
			$pendingChanges->where('change_id', '!=', $skipChange->change_id);
		}

		foreach ($pendingChanges->fetch() AS $pendingChange)
		{
			$pendingChange->whenSaveable(function (UsernameChange $pendingChange) use ($user, $moderator)
			{
				if ($pendingChange->change_state != 'moderated')
				{
					return;
				}

				if ($pendingChange->new_username == $user->username)
				{
					// the change is identical so delete original
					$pendingChange->delete();
				}
				else
				{
					// assume this change supersedes the previously requested change so reject original
					$pendingChange->change_state = 'rejected';
					$pendingChange->moderator_user_id = $moderator->is_moderator ? $moderator->user_id : 0;

					$rejectReason = \XF::phrase('another_username_change_superseded_this_request');
					$pendingChange->reject_reason = Str::substr($rejectReason->render(), 0, 200);

					$pendingChange->save();
				}
			});
		}
	}

	public function rebuildLastUsernameChange(User $user)
	{
		$max = $this->db()->fetchOne("
			SELECT MAX(change_date)
			FROM xf_username_change
			WHERE user_id = ?
				AND change_state = 'approved'
		", $user->user_id);

		$user->fastUpdate('username_date', $max ?: 0);
	}

	public function rebuildLastVisibleUsernameChange(User $user)
	{
		$max = $this->db()->fetchOne("
			SELECT MAX(change_date)
			FROM xf_username_change
			WHERE user_id = ?
				AND change_state = 'approved'
				AND visible = 1
		", $user->user_id);

		$user->fastUpdate('username_date_visible', $max ?: 0);
	}
}
