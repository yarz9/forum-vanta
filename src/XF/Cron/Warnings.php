<?php

namespace XF\Cron;

use XF\Repository\WarningRepository;

class Warnings
{
	public static function expireWarnings()
	{
		\XF::repository(WarningRepository::class)->processExpiredWarnings();
	}
}
