<?php

namespace XF\Service\Thread;

use XF\App;
use XF\Behavior\Indexable;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\PrintableException;
use XF\Repository\ThreadRedirectRepository;
use XF\Repository\ThreadRepository;
use XF\Service\AbstractService;
use XF\Service\FeaturedContent\CreatorService;
use XF\Service\FeaturedContent\DeleterService;
use XF\Service\ModerationAlertSendableTrait;
use XF\Service\Post\NotifierService;

use function call_user_func, intval;

class MoverService extends AbstractService
{
	use ModerationAlertSendableTrait;

	/**
	 * @var Thread
	 */
	protected $thread;

	protected $alert = false;
	protected $alertReason = '';

	protected $notifyWatchers = false;

	protected $redirect = false;
	protected $redirectLength = 0;

	protected $prefixId = null;

	protected $extraSetup = [];

	public function __construct(App $app, Thread $thread)
	{
		parent::__construct($app);

		$this->thread = $thread;
	}

	public function getThread()
	{
		return $this->thread;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function setRedirect($redirect, $length = null)
	{
		$this->redirect = (bool) $redirect;
		if ($length !== null)
		{
			$this->redirectLength = intval($length);
		}
	}

	public function setPrefix($prefixId)
	{
		$this->prefixId = ($prefixId === null ? $prefixId : intval($prefixId));
	}

	public function setNotifyWatchers($value = true)
	{
		$this->notifyWatchers = (bool) $value;
	}

	public function addExtraSetup(callable $extra)
	{
		$this->extraSetup[] = $extra;
	}

	public function move(Forum $forum)
	{
		$actor = \XF::visitor();

		$thread = $this->thread;
		$oldForum = $thread->Forum;

		$moved = ($thread->node_id != $forum->node_id);

		if ($this->alert)
		{
			$wasVisibleForAlert = $this->isContentVisibleToContentAuthor(
				$thread,
				$thread
			);
		}
		else
		{
			$wasVisibleForAlert = false;
		}

		foreach ($this->extraSetup AS $extra)
		{
			call_user_func($extra, $thread, $forum);
		}

		$thread->node_id = $forum->node_id;
		if ($this->prefixId !== null)
		{
			$thread->prefix_id = $this->prefixId;
		}

		if (!$thread->preSave())
		{
			throw new PrintableException($thread->getErrors());
		}

		$thread->getBehavior(Indexable::class)->setOption('skipIndexNow', true);

		$db = $this->db();
		$db->beginTransaction();

		$thread->save(true, false);

		if ($moved)
		{
			if ($thread->isFeatured() && $thread->Feature)
			{
				$feature = $thread->Feature;
				if ($feature->auto_featured && !$forum->auto_feature)
				{
					/** @var DeleterService $deleter */
					$deleter = $this->service(
						DeleterService::class,
						$feature
					);
					$deleter->delete();
				}
				else
				{
					$feature->fastUpdate(
						'content_container_id',
						$forum->node_id
					);
				}
			}
			else if ($forum->auto_feature)
			{
				/** @var CreatorService $creator */
				$creator = $this->service(
					CreatorService::class,
					$thread
				);
				$creator->setAutoFeatured();
				$creator->save();
			}

			if ($this->redirect && $oldForum)
			{
				/** @var ThreadRedirectRepository $redirectRepo */
				$redirectRepo = $this->repository(ThreadRedirectRepository::class);
				$redirectRepo->createThreadRedirectionDouble($thread, $oldForum, $this->redirectLength);
			}
		}

		$db->commit();

		if ($this->alert)
		{
			$isVisibleForAlert = $this->isContentVisibleToContentAuthor(
				$thread,
				$thread
			);
		}
		else
		{
			$isVisibleForAlert = false;
		}

		if ($moved
			&& $thread->discussion_state == 'visible'
			&& $this->alert
			&& $thread->user_id != $actor->user_id
			&& $thread->discussion_type != 'redirect'
			&& ($wasVisibleForAlert || $isVisibleForAlert)
		)
		{
			/** @var ThreadRepository $threadRepo */
			$threadRepo = $this->repository(ThreadRepository::class);
			$threadRepo->sendModeratorActionAlert($thread, 'move', $this->alertReason);
		}

		if ($moved
			&& $this->notifyWatchers
			&& $thread->FirstPost
			&& $thread->discussion_type != 'redirect'
		)
		{
			/** @var NotifierService $notifier */
			$notifier = $this->service(NotifierService::class, $thread->FirstPost, 'thread');
			if ($oldForum)
			{
				$notifier->skipUsersWatchingForum($oldForum);
			}
			$notifier->notifyAndEnqueue(3);
		}

		return $moved;
	}
}
