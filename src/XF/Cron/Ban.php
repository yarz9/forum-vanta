<?php

namespace XF\Cron;

use XF\Repository\BanningRepository;

/**
 * Cron entry for cleaning up bans.
 */
class Ban
{
	/**
	 * Deletes expired bans.
	 */
	public static function deleteExpiredBans()
	{
		\XF::app()->repository(BanningRepository::class)->deleteExpiredUserBans();
	}
}
