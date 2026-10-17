<?php

namespace XF\Cron;

use XF\Job\ActivitySummaryEmail;
use XF\Repository\ActivitySummaryRepository;

class ActivitySummary
{
	public static function triggerActivitySummaryEmail()
	{
		$activitySummaryEmail = \XF::options()->activitySummaryEmail;
		if (empty($activitySummaryEmail['enabled']))
		{
			return;
		}

		if (\XF::app()->import()->manager()->isImportRunning())
		{
			// do not allow activity summary email to be sent while an import is in progress
			return;
		}

		/** @var ActivitySummaryRepository $repo */
		$repo = \XF::repository(ActivitySummaryRepository::class);

		$sections = $repo->findActivitySummarySectionsForDisplay()->fetch();

		if (!$sections->count())
		{
			return;
		}

		$userIds = $repo->getActivitySummaryRecipientIds();

		\XF::app()->jobManager()->enqueueUnique('activitySummaryEmail', ActivitySummaryEmail::class, [
			'user_ids' => $userIds,
			'section_ids' => $sections->keys(),
		], false, 50);
	}
}
