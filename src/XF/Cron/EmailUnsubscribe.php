<?php

namespace XF\Cron;

class EmailUnsubscribe
{
	public static function process()
	{
		if (!static::canProcessUnsubscribeEmails())
		{
			return;
		}

		\XF::app()->jobManager()->enqueueUnique('EmailUnsubscribe', \XF\Job\EmailUnsubscribe::class, [], false);
	}

	protected static function canProcessUnsubscribeEmails(): bool
	{
		if (!\XF::config('enableMail'))
		{
			return false;
		}

		$options = \XF::options();
		$handler = $options->emailUnsubscribeHandler;
		$unsubHandling = $options->unsubscribeEmailHandling;
		$unsubEmail = $options->unsubscribeEmailAddress;

		return $handler && !empty($handler['enabled']) && !empty($unsubHandling['email']) && $unsubEmail;
	}
}
