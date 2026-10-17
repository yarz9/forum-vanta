<?php

namespace XF\Cron;

use XF\Repository\AttachmentRepository;
use XF\Repository\ThreadRepository;

class Views
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var ThreadRepository $threadRepo */
		$threadRepo = $app->repository(ThreadRepository::class);
		$threadRepo->batchUpdateThreadViews();

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = $app->repository(AttachmentRepository::class);
		$attachmentRepo->batchUpdateAttachmentViews();
	}
}
