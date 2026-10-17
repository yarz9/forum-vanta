<?php

namespace XF\EmailStop;

use XF\Entity\Thread;
use XF\Entity\User;
use XF\Repository\ForumWatchRepository;
use XF\Repository\ThreadWatchRepository;

class ThreadHandler extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var Thread|null $thread */
		$thread = \XF::em()->find(Thread::class, $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($thread) { return $thread && $thread->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $thread->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(User $user)
	{
		return \XF::phrase('stop_notification_emails_from_all_threads');
	}

	public function stopOne(User $user, $contentId)
	{
		/** @var Thread $thread */
		$thread = \XF::em()->find(Thread::class, $contentId);
		if ($thread)
		{
			/** @var ThreadWatchRepository $threadWatchRepo */
			$threadWatchRepo = \XF::repository(ThreadWatchRepository::class);
			$threadWatchRepo->setWatchState($thread, $user, 'no_email');
		}
	}

	public function stopAll(User $user)
	{
		// Note that we stop all thread and forum notifications here, as the distinction of the source is unlikely
		// to be clear and they've chosen to stop all emails of this type.
		/** @var ThreadWatchRepository $threadWatchRepo */
		$threadWatchRepo = \XF::repository(ThreadWatchRepository::class);
		$threadWatchRepo->setWatchStateForAll($user, 'no_email');

		/** @var ForumWatchRepository $forumWatchRepo */
		$forumWatchRepo = \XF::repository(ForumWatchRepository::class);
		$forumWatchRepo->setWatchStateForAll($user, 'no_email');
	}
}
