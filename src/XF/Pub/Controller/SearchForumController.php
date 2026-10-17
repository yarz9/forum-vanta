<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\NodePlugin;
use XF\Entity\SearchForum;
use XF\Entity\SessionActivity;
use XF\Finder\SearchForumFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\NodeRepository;
use XF\Repository\SearchForumRepository;

use function count, intval, is_int, strval;

class SearchForumController extends AbstractController
{
	/**
	 * @return AbstractReply
	 */
	public function actionIndex(ParameterBag $params)
	{
		if (!$params->node_id && !$params->node_name)
		{
			return $this->redirectPermanently($this->buildLink('forums'));
		}

		return $this->rerouteController(self::class, 'view', $params);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionView(ParameterBag $params)
	{
		$searchForum = $this->assertViewableSearchForum(
			$params->node_id ?: $params->node_name,
			$this->getSearchForumViewExtraWith()
		);

		if ($this->responseType == 'rss')
		{
			return $this->getSearchForumRss($searchForum);
		}

		$page = $this->filterPage($params->page);
		$perPage = $this->options()->discussionsPerPage;

		$this->assertCanonicalUrl(
			$this->buildLink('search-forums', $searchForum, ['page' => $page])
		);

		$isRebuildPending = $this->getSearchForumRepo()->enqueueCacheRebuildIfNeeded($searchForum);
		$userCache = $searchForum->getUserCacheForUser(\XF::visitor(), $isRebuildPending);

		$total = $userCache->result_count;

		$this->assertValidPage(
			$page,
			$perPage,
			$total,
			'search-forums',
			$searchForum
		);

		$threads = $userCache->getThreadsByPage($page, $perPage, $this->getUserCacheExtraWith($searchForum));

		$canInlineMod = false;
		foreach ($threads AS $thread)
		{
			if ($thread->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		/** @var NodeRepository $nodeRepo */
		$nodeRepo = $this->repository(NodeRepository::class);
		$nodes = $nodeRepo->getNodeList($searchForum->Node);
		$nodeTree = count($nodes)
			? $nodeRepo->createNodeTree($nodes, $searchForum->node_id)
			: null;
		$nodeExtras = $nodeTree
			? $nodeRepo->getNodeListExtras($nodeTree)
			: null;

		$viewParams = [
			'searchForum' => $searchForum,

			'nodeTree' => $nodeTree,
			'nodeExtras' => $nodeExtras,

			'threads' => $threads,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,

			'canInlineMod' => $canInlineMod,
		];
		return $this->view(
			'XF:SearchForum\View',
			'search_forum_view',
			$viewParams
		);
	}

	/**
	 * @return string[]
	 */
	protected function getSearchForumViewExtraWith()
	{
		$visitor = \XF::visitor();
		return ['Cache', "UserCaches|{$visitor->user_id}"];
	}

	/**
	 * @return string[]
	 */
	protected function getUserCacheExtraWith(SearchForum $searchForum): array
	{
		return [];
	}

	/**
	 * @return AbstractReply
	 */
	protected function getSearchForumRss(SearchForum $searchForum)
	{
		$limit = $this->options()->discussionsPerPage;

		$isRebuildPending = $this->getSearchForumRepo()->enqueueCacheRebuildIfNeeded($searchForum);
		$userCache = $searchForum->getUserCacheForUser(\XF::visitor(), $isRebuildPending);

		$viewParams = [
			'searchForum' => $searchForum,
			'threads' => $userCache->getThreadsByPage(1, $limit),
		];
		return $this->view(
			'XF:SearchForum\Rss',
			'',
			$viewParams
		);
	}

	/**
	 * @param int|string $nodeIdOrName
	 * @param string[]   $extraWith
	 *
	 * @return SearchForum
	 *
	 * @throws Exception
	 */
	protected function assertViewableSearchForum(
		$nodeIdOrName,
		array $extraWith = []
	)
	{
		if ($nodeIdOrName === null)
		{
			throw $this->exception($this->notFound(
				\XF::phrase('requested_search_forum_not_found')
			));
		}

		$finder = $this->finder(SearchForumFinder::class);

		if (
			is_int($nodeIdOrName) ||
			$nodeIdOrName === strval(intval($nodeIdOrName))
		)
		{
			$finder->where('node_id', $nodeIdOrName);
		}
		else
		{
			$finder->where([
				'Node.node_name' => $nodeIdOrName,
				'Node.node_type_id' => 'SearchForum',
			]);
		}

		$visitor = \XF::visitor();
		$finder
			->with('Node', true)
			->with("Node.Permissions|{$visitor->permission_combination_id}")
			->with($extraWith);

		/** @var SearchForum $searchForum */
		$searchForum = $finder->fetchOne();
		if (!$searchForum)
		{
			throw $this->exception(
				$this->notFound(
					\XF::phrase('requested_search_forum_not_found')
				)
			);
		}
		if (!$searchForum->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin(NodePlugin::class)->applyNodeContext($searchForum->Node);
		$this->setContentKey('search_forum-' . $searchForum->node_id);

		return $searchForum;
	}

	/**
	 * @return SearchForumRepository
	 */
	protected function getSearchForumRepo()
	{
		return $this->repository(SearchForumRepository::class);
	}

	/**
	 * @param SessionActivity[] $activities
	 *
	 * @return array
	 */
	public static function getActivityDetails(array $activities)
	{
		return NodePlugin::getNodeActivityDetails(
			$activities,
			'SearchForum',
			\XF::phrase('viewing_search_forum')
		);
	}
}
