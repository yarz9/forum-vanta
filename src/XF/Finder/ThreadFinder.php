<?php

namespace XF\Finder;

use XF\Entity\Forum;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Repository\ThreadRepository;

/**
 * @method AbstractCollection<\XF\Entity\Thread> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Thread> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Thread|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Thread>
 */
class ThreadFinder extends Finder
{
	public function inForum(Forum $forum, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false,
		], $limits);

		$this->where('node_id', $forum->node_id);

		$this->applyForumDefaultOrder($forum);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInForum($forum, $limits['allowOwnPending']);
		}

		return $this;
	}

	public function applyVisibilityChecksInForum(Forum $forum, $allowOwnPending = false)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($forum->canViewDeletedThreads())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($forum->canViewModeratedThreads())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'discussion_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['discussion_state', $viewableStates];

		$this->whereOr($conditions);

		$visitor = \XF::visitor();
		if (!$visitor->hasNodePermission($forum->node_id, 'viewOthers'))
		{
			if ($visitor->user_id)
			{
				$this->where('user_id', $visitor->user_id);
			}
			else
			{
				$this->whereSql('1=0'); // force false immediately
			}
		}

		return $this;
	}

	public function applyForumDefaultOrder(Forum $forum)
	{
		$sortOrders = $forum->TypeHandler->getThreadListSortOptions($forum);
		$sortOrder = $sortOrders[$forum->default_sort_order] ?? 'last_post_date';

		$this->setDefaultOrder($sortOrder, $forum->default_sort_direction);

		return $this;
	}

	public function withReadData($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}

		if ($userId)
		{
			$this->with([
				'Read|' . $userId,
				'Forum.Read|' . $userId,
			]);
		}

		return $this;
	}

	public function unreadOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, no read tracking
			return $this;
		}

		$threadReadExpression = $this->expression(
			'%s > COALESCE(%s, 0)',
			'last_post_date',
			'Read|' . $userId . '.thread_read_date'
		);

		$forumReadExpression = $this->expression(
			'%s > COALESCE(%s, 0)',
			'last_post_date',
			'Forum.Read|' . $userId . '.forum_read_date'
		);

		/** @var ThreadRepository $threadRepo */
		$threadRepo = $this->em->getRepository(ThreadRepository::class);

		$this->where('last_post_date', '>', $threadRepo->getReadMarkingCutOff())
			->where($threadReadExpression)
			->where($forumReadExpression);

		return $this;
	}

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, just ignore
			return $this;
		}

		$this->whereOr(
			['Watch|' . $userId . '.user_id', '!=', null],
			['Forum.Watch|' . $userId . '.user_id', '!=', null]
		);

		return $this;
	}

	public function skipIgnored(?User $user = null)
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		if (!$user->user_id)
		{
			return $this;
		}

		if ($user->Profile && $user->Profile->ignored)
		{
			$this->where('user_id', '<>', array_keys($user->Profile->ignored));
		}

		return $this;
	}
}
