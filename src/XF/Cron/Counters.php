<?php

namespace XF\Cron;

use XF\Repository\CountersRepository;
use XF\Repository\StatsRepository;

/**
 * Cron entry for timed counter updates.
 */
class Counters
{
	/**
	 * Rebuilds the board totals counter.
	 */
	public static function rebuildForumStatistics()
	{
		/** @var CountersRepository $countersRepo */
		$countersRepo = \XF::app()->repository(CountersRepository::class);
		$countersRepo->rebuildForumStatisticsCache();
	}

	/**
	 * Log daily statistics
	 */
	public static function recordDailyStats()
	{
		/** @var StatsRepository $statsRepo */
		$statsRepo = \XF::app()->repository(StatsRepository::class);

		// get the the timestamp of 00:00 UTC for today
		$time = \XF::$time - \XF::$time % 86400;
		$statsRepo->build($time - 86400, $time);
	}
}
