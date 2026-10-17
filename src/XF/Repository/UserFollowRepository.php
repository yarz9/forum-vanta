<?php

namespace XF\Repository;

use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Finder\UserFollowFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserFollowRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findFollowingForProfile(User $user)
	{
		return $this->finder(UserFollowFinder::class)
			->with('FollowUser', true)
			->with('FollowUser.Profile', true)
			->with('FollowUser.Option', true)
			->where('FollowUser.is_banned', false)
			->where('FollowUser.user_state', 'valid')
			->where('user_id', $user->user_id);
	}

	/**
	 * @return Finder
	 */
	public function findFollowersForProfile(User $user)
	{
		return $this->finder(UserFollowFinder::class)
			->with('User', true)
			->with('User.Profile', true)
			->with('User.Option', true)
			->where('User.is_banned', false)
			->where('User.user_state', 'valid')
			->where('follow_user_id', $user->user_id);
	}

	public function rebuildFollowingCache($userId)
	{
		$following = $this->db()->fetchAllColumn("
			SELECT follow_user_id
			FROM xf_user_follow
			WHERE user_id = ?
			AND follow_user_id <> ?
		", [$userId, $userId]);

		$profile = $this->em->find(UserProfile::class, $userId);
		if ($profile)
		{
			$profile->fastUpdate('following', $following);
		}

		return $following;
	}
}
