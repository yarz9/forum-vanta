<?php

namespace XF\Cron;

use XF\Finder\UserFinder;
use XF\Repository\TrophyRepository;

/**
 * Cron entry for manipulating trophies.
 */
class Trophy
{
	/**
	 * Runs the cron-based check for new trophies that users should be awarded.
	 */
	public static function runTrophyCheck()
	{
		if (!\XF::options()->enableTrophies)
		{
			return;
		}

		/** @var TrophyRepository $trophyRepo */
		$trophyRepo = \XF::repository(TrophyRepository::class);
		$trophies = $trophyRepo->findTrophiesForList()->fetch();
		if (!$trophies)
		{
			return;
		}

		$userFinder = \XF::finder(UserFinder::class);

		$users = $userFinder
			->where('last_activity', '>=', time() - 2 * 3600)
			->isValidUser(false)
			->fetch();

		$userTrophies = $trophyRepo->findUsersTrophies($users->keys())->fetch()->groupBy('user_id');
		foreach ($users AS $user)
		{
			$trophyRepo->updateTrophiesForUser($user, $userTrophies[$user->user_id] ?? [], $trophies);
		}
	}
}
