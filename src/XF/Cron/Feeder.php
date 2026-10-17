<?php

namespace XF\Cron;

use XF\Repository\FeedRepository;

/**
 * Cron entry for feed importer.
 */
class Feeder
{
	/**
	 * Imports feeds.
	 */
	public static function importFeeds()
	{
		$app = \XF::app();

		/** @var FeedRepository $feedRepo */
		$feedRepo = $app->repository(FeedRepository::class);

		$dueFeeds = $feedRepo->findDueFeeds()->fetch();
		if ($dueFeeds->count())
		{
			$app->jobManager()->enqueueUnique('feederImport', \XF\Job\Feeder::class, [], false);
		}
	}
}
