<?php

namespace XF\Repository;

use XF\Entity\SearchForum;
use XF\Entity\SearchForumCache;
use XF\Entity\User;
use XF\Finder\ThreadFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\Searcher\Thread;
use XF\Util\Arr;

class SearchForumRepository extends Repository
{
	/**
	 * @return ThreadFinder
	 */
	public function findThreadsForSearchForum(
		SearchForum $searchForum
	): ThreadFinder
	{
		$searcher = $this->app()->searcher(
			Thread::class,
			$searchForum->search_criteria
		);

		$searcher->setOrder(
			$searchForum->sort_order,
			$searchForum->sort_direction
		);

		return $searcher->getFinder();
	}

	public function setupFinderForSearchForum(ThreadFinder $finder)
	{
		$visitor = \XF::visitor();

		$finder
			->with('fullForum')
			->with("Forum.Node.Permissions|{$visitor->permission_combination_id}");

		if ($visitor->is_moderator)
		{
			$finder->with('DeletionLog');
		}
	}

	/**
	 * @return AbstractCollection|\XF\Entity\Thread[]
	 */
	public function rebuildThreadsForSearchForum(
		SearchForum $searchForum
	)
	{
		$finder = $this->findThreadsForSearchForum($searchForum);
		$this->setupFinderForSearchForum($finder);
		// 2x fudge factor
		$limit = $searchForum->max_results * 2;
		$threads = $finder->fetch($limit);

		$countedThreads = $threads->slice(0, $searchForum->max_results);
		$discussionCount = $countedThreads->count();
		$replyCount = array_sum($countedThreads->pluckNamed('reply_count'));

		if ($searchForum->sort_order == 'last_post_date' && $searchForum->sort_direction == 'desc')
		{
			$latestThread = $threads->first();
		}
		else
		{
			$sortedThreads = Arr::columnSort($threads->toArray(), 'last_post_date');
			$latestThread = end($sortedThreads);
		}

		$db = $this->db();
		$db->beginTransaction();

		// lock this here to try to avoid potential race conditions if there are competing rebuild actions
		$db->fetchRow("
			SELECT *
			FROM xf_search_forum
			WHERE node_id = ?
			FOR UPDATE
		", $searchForum->node_id);

		$searchForum->discussion_count = $discussionCount;
		$searchForum->message_count = $discussionCount + $replyCount;
		$searchForum->last_post_id = $latestThread->last_post_id ?? 0;
		$searchForum->last_post_date = $latestThread->last_post_date ?? 0;
		$searchForum->last_post_user_id = $latestThread->last_post_user_id ?? 0;
		$searchForum->last_post_username = $latestThread->last_post_username ?? '';
		$searchForum->last_thread_id = $latestThread->thread_id ?? 0;
		$searchForum->last_thread_title = $latestThread->title ?? '';
		$searchForum->last_thread_prefix_id = $latestThread->prefix_id ?? 0;
		$searchForum->save(true, false);

		/** @var SearchForumCache $nodeCache */
		$nodeCache = $searchForum->getRelationOrDefault('Cache');
		$nodeCache->results = $threads->keys();
		$nodeCache->save(true, false);

		$db->delete(
			'xf_search_forum_cache_user',
			'node_id = ?',
			$searchForum->node_id
		);

		$db->commit();

		return $threads;
	}

	public function getThreadIdsForUserCache(SearchForum $searchForum, User $user): array
	{
		$cache = $searchForum->Cache;
		if (!$cache)
		{
			// updating the cache is handled elsewhere
			return [];
		}

		$threads = $this->getThreadsByIdsOrdered($cache->results);

		\XF::asVisitor($user, function () use (&$threads)
		{
			$threads = $threads->filterViewable();
		});

		return $threads->slice(0, $searchForum->max_results)
			->keys();
	}

	/**
	 * @param int[] $threadIds
	 * @param string[] $extraWith
	 *
	 * @return AbstractCollection|\XF\Entity\Thread[]
	 */
	public function getThreadsByIdsOrdered(array $threadIds, array $extraWith = [])
	{
		$finder = $this->finder(ThreadFinder::class)->whereIds($threadIds);
		$this->setupFinderForSearchForum($finder);

		if ($extraWith)
		{
			$finder->with($extraWith);
		}

		return $finder->fetch()->sortByList($threadIds);
	}

	public function enqueueCacheRebuildIfNeeded(SearchForum $searchForum): bool
	{
		$isRebuildNeeded = $searchForum->isCacheRebuildNeeded();
		if ($isRebuildNeeded)
		{
			$this->app()->jobManager()->enqueueUnique(
				'searchForumCache' . $searchForum->node_id,
				\XF\Job\SearchForumCache::class,
				['node_id' => $searchForum->node_id],
				false
			);
		}

		return $isRebuildNeeded;
	}
}
