<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;

class CountersRepository extends Repository
{
	public function getForumStatisticsCacheData()
	{
		$cache = [];

		/** @var ForumRepository $forumRepo */
		$forumRepo = $this->repository(ForumRepository::class);
		$cache += $forumRepo->getForumCounterTotals();

		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);

		$cache['users'] = $userRepo->findValidUsers()->total();

		$latestUser = $userRepo->getLatestValidUser();
		$cache['latestUser'] = $latestUser ? $latestUser->toArray() : null;

		return $cache;
	}

	public function rebuildForumStatisticsCache()
	{
		$cache = $this->getForumStatisticsCacheData();
		\XF::registry()->set('forumStatistics', $cache);
		return $cache;
	}
}
