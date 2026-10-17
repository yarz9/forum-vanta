<?php

namespace XF\EmailStop;

use XF\Entity\Forum;
use XF\Entity\User;
use XF\Repository\ForumWatchRepository;
use XF\Repository\ThreadWatchRepository;

class ForumHandler extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var Forum|null $forum */
		$forum = \XF::em()->find(Forum::class, $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($forum) { return $forum && $forum->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $forum->title]);
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
		/** @var Forum $forum */
		$forum = \XF::em()->find(Forum::class, $contentId);
		if ($forum)
		{
			/** @var ForumWatchRepository $forumWatchRepo */
			$forumWatchRepo = \XF::repository(ForumWatchRepository::class);
			$forumWatchRepo->setWatchState($forum, $user, null, null, false);
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
