<?php

namespace XF\Cron;

use XF\Repository\EmailBounceRepository;

class EmailBounce
{
	public static function process()
	{
		/** @var EmailBounceRepository $bounceRepo */
		$bounceRepo = \XF::repository(EmailBounceRepository::class);
		$bounceRepo->pruneEmailBounceLogs();
		$bounceRepo->pruneSoftBounceHistory();

		if (!self::canProcessEmailBounce())
		{
			return;
		}

		\XF::app()->jobManager()->enqueueUnique('EmailBounce', \XF\Job\EmailBounce::class, [], false);
	}

	protected static function canProcessEmailBounce(): bool
	{
		if (!\XF::config('enableMail'))
		{
			return false;
		}

		$handler = \XF::options()->emailBounceHandler;

		return !empty($handler);
	}
}
