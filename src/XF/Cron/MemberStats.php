<?php

namespace XF\Cron;

use XF\Repository\MemberStatRepository;
use XF\Service\MemberStat\PreparerService;

class MemberStats
{
	public static function rebuildMemberStatsCache()
	{
		/** @var MemberStatRepository $memberStatsRepo */
		$memberStatsRepo = \XF::app()->repository(MemberStatRepository::class);
		$finder = $memberStatsRepo->findCacheableMemberStats();

		foreach ($finder->fetch() AS $memberStat)
		{
			if (\XF::$time > $memberStat->cache_expiry)
			{
				/** @var PreparerService $preparer */
				$preparer = \XF::app()->service(PreparerService::class, $memberStat);
				$preparer->cache();
			}
		}
	}
}
