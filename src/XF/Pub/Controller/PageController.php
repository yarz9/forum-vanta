<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\BookmarkPlugin;
use XF\ControllerPlugin\NodePlugin;
use XF\Entity\Page;
use XF\Entity\SessionActivity;
use XF\Finder\PageFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\NodeRepository;
use XF\Repository\PageRepository;

use function call_user_func_array;

class PageController extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		$page = $this->assertViewablePage($params->node_name);
		$pageRepo = $this->getPageRepo();
		$nodeRepo = $this->getNodeRepo();

		$this->assertCanonicalUrl($this->buildLink('pages', $page));

		if ($page->log_visits)
		{
			$pageRepo->logView($page, \XF::visitor());
		}

		$siblings = $page->list_siblings ? $nodeRepo->findSiblings($page->Node)->fetch() : null;
		$children = $page->list_children ? $nodeRepo->findChildren($page->Node)->fetch() : null;

		$testNodes = $this->em()->getEmptyCollection();
		if ($siblings)
		{
			$testNodes = $testNodes->merge($siblings);
		}
		if ($children)
		{
			$testNodes = $testNodes->merge($children);
		}
		if ($testNodes->count())
		{
			$nodeRepo->loadNodeTypeDataForNodes($testNodes);
		}
		if ($siblings)
		{
			$siblings = $nodeRepo->filterViewable($siblings);
		}
		if ($children)
		{
			$children = $nodeRepo->filterViewable($children);
		}

		$viewParams = [
			'page' => $page,
			'parent' => $page->Node->Parent,
			'siblings' => $siblings,
			'children' => $children,
		];
		$reply = $this->view('XF:Page\View', 'page_view', $viewParams);

		if ($page->callback_class && $page->callback_method)
		{
			call_user_func_array([$page->callback_class, $page->callback_method], [$this, &$reply]);
		}

		return $reply;
	}

	public function actionBookmark(ParameterBag $params)
	{
		$page = $this->assertViewablePage($params->node_name);
		$node = $page->Node;

		/** @var BookmarkPlugin $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin(BookmarkPlugin::class);

		return $bookmarkPlugin->actionBookmark(
			$node,
			$this->buildLink('pages/bookmark', $node)
		);
	}

	/**
	 * @param string $nodeName
	 * @param array $extraWith
	 *
	 * @return Page
	 *
	 * @throws Exception
	 */
	protected function assertViewablePage($nodeName, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$extraWith[] = 'Node.Permissions|' . $visitor->permission_combination_id;

		$finder = $this->em()->getFinder(PageFinder::class)
			->with('Node', true)
			->with('Node.Parent')
			->with($extraWith)
			->where([
				'Node.node_name' => $nodeName,
				'Node.node_type_id' => 'Page',
			]);

		/** @var Page $page */
		$page = $finder->fetchOne();
		if (!$page)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_page_not_found')));
		}
		if (!$page->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin(NodePlugin::class)->applyNodeContext($page->Node);
		$this->setContentKey('page-' . $page->node_id);

		return $page;
	}

	/**
	 * @return NodeRepository
	 */
	protected function getNodeRepo()
	{
		return $this->repository(NodeRepository::class);
	}

	/**
	 * @return PageRepository
	 */
	protected function getPageRepo()
	{
		return $this->repository(PageRepository::class);
	}

	/**
	 * @param SessionActivity[] $activities
	 */
	public static function getActivityDetails(array $activities)
	{
		return NodePlugin::getNodeActivityDetails(
			$activities,
			'Page',
			\XF::phrase('viewing_page')
		);
	}

	// in case these have custom URL which is a page node
	public function assertPolicyAcceptance($action)
	{
	}
}
