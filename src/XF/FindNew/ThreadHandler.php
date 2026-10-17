<?php

namespace XF\FindNew;

use XF\Entity\FindNew;
use XF\Entity\Thread;
use XF\Finder\ThreadFinder;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	public function getRoute()
	{
		// This returns threads, so we attach to the thread content type. However, as it's really individual posts
		// that generally bump things up, we refer to this in the interface as "new posts".
		return 'whats-new/posts';
	}

	public function getPageReply(Controller $controller, FindNew $findNew, array $results, $page, $perPage)
	{
		$canInlineMod = false;

		foreach ($results AS $thread)
		{
			if ($thread->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'threads' => $results,
			'canInlineMod' => $canInlineMod,
		];
		return $controller->view('XF:WhatsNew\Posts', 'whats_new_posts', $viewParams);
	}

	public function getFiltersFromInput(Request $request)
	{
		$filters = [];

		$visitor = \XF::visitor();

		$unread = $request->filter('unread', 'bool');
		if ($unread && $visitor->user_id)
		{
			$filters['unread'] = true;
		}

		$watched = $request->filter('watched', 'bool');
		if ($watched && $visitor->user_id)
		{
			$filters['watched'] = true;
		}

		$participated = $request->filter('participated', 'bool');
		if ($participated && $visitor->user_id)
		{
			$filters['participated'] = true;
		}

		$started = $request->filter('started', 'bool');
		if ($started && $visitor->user_id)
		{
			$filters['started'] = true;
		}

		$unanswered = $request->filter('unanswered', 'bool');
		if ($unanswered)
		{
			$filters['unanswered'] = true;
		}

		return $filters;
	}

	public function getDefaultFilters()
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			return ['unread' => true];
		}
		else
		{
			return [];
		}
	}

	public function getResultIds(array $filters, $maxResults)
	{
		$visitor = \XF::visitor();

		$threadFinder = \XF::finder(ThreadFinder::class)
			->with('Forum', true)
			->with('Forum.Node.Permissions|' . $visitor->permission_combination_id)
			->where('Forum.find_new', true)
			->where('discussion_type', '<>', 'redirect')
			->where('discussion_state', '<>', 'deleted')
			->order('last_post_date', 'DESC');

		$this->applyFilters($threadFinder, $filters);

		$threads = $threadFinder->fetch($maxResults);
		$threads = $this->filterResults($threads);

		// TODO: consider overfetching or some other permission limits within the query

		return $threads->keys();
	}

	public function getPageResultsEntities(array $ids)
	{
		$visitor = \XF::visitor();

		$ids = array_map('intval', $ids);

		$threadFinder = \XF::finder(ThreadFinder::class)
			->where('thread_id', $ids)
			->with('fullForum')
			->with('Forum.Node.Permissions|' . $visitor->permission_combination_id);

		return $threadFinder->fetch();
	}

	protected function filterResults(AbstractCollection $results)
	{
		return $results->filter(function (Thread $thread)
		{
			return ($thread->canView() && !$thread->isIgnored());
		});
	}

	protected function applyFilters(ThreadFinder $threadFinder, array $filters)
	{
		$visitor = \XF::visitor();

		if (!empty($filters['unread']))
		{
			$threadFinder->unreadOnly($visitor->user_id);
		}
		else
		{
			$threadFinder->where('last_post_date', '>', \XF::$time - (86400 * \XF::options()->readMarkingDataLifetime));
		}

		if (!empty($filters['watched']))
		{
			$threadFinder->watchedOnly($visitor->user_id);
		}

		if (!empty($filters['participated']))
		{
			$threadFinder->exists('UserPosts|' . $visitor->user_id);
		}

		if (!empty($filters['started']))
		{
			$threadFinder->where('user_id', $visitor->user_id);
		}

		if (!empty($filters['unanswered']))
		{
			$threadFinder->where('reply_count', 0);
		}
	}

	public function getResultsPerPage()
	{
		return \XF::options()->discussionsPerPage;
	}
}
