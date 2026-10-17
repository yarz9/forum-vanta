<?php

namespace XF\Pub\Controller;

use XF\Entity\SessionActivity;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Finder\ThreadFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\ForumRepository;
use XF\Repository\ThreadRepository;

class FindThreadsController extends AbstractController
{
	protected $user = null;

	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertRegistrationRequired();
	}

	public function actionIndex()
	{
		switch ($this->filter('type', 'str'))
		{
			case 'started':
				return $this->redirectPermanently($this->buildLink('find-threads/started'));
			case 'contributed':
				return $this->redirectPermanently($this->buildLink('find-threads/contributed'));
			case 'unanswered':
			default:
				return $this->redirectPermanently($this->buildLink('find-threads/unanswered'));
		}
	}

	public function actionUnanswered()
	{
		$threadFinder = $this->getThreadRepo()->findThreadsWithNoReplies();

		return $this->getThreadResults($threadFinder, 'unanswered');
	}

	public function actionStarted()
	{
		if (!$userId = $this->getUserId())
		{
			$this->assertRegistrationRequired();
		}

		$threadFinder = $this->getThreadRepo()->findThreadsStartedByUser($userId);

		return $this->getThreadResults($threadFinder, 'started');
	}

	public function actionContributed()
	{
		if (!$userId = $this->getUserId())
		{
			$this->assertRegistrationRequired();
		}

		$threadFinder = $this->getThreadRepo()->findThreadsWithPostsByUser($userId);

		return $this->getThreadResults($threadFinder, 'contributed');
	}

	protected function getThreadResults(ThreadFinder $threadFinder, $pageSelected)
	{
		$this->setSectionContext('forums');

		$forums = $this->repository(ForumRepository::class)->getViewableForums();

		$page = $this->filterPage();
		$perPage = $this->options()->discussionsPerPage;

		$threadFinder
			->where('discussion_state', 'visible')
			->where('node_id', $forums->keys())
			->limitByPage($page, $perPage);

		$total = $threadFinder->total();
		$threads = $threadFinder->fetch()->filterViewable();

		/** @var Thread $thread */
		$canInlineMod = false;
		foreach ($threads AS $threadId => $thread)
		{
			if ($thread->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'threads' => $threads->filterViewable(),
			'canInlineMod' => $canInlineMod,
			'user' => $this->user,
			'pageSelected' => $pageSelected,
		];
		return $this->view('XF:FindThreads\List', 'find_threads_list', $viewParams);
	}

	/**
	 * @return ThreadRepository
	 */
	protected function getThreadRepo()
	{
		return $this->repository(ThreadRepository::class);
	}

	protected function getUserId()
	{
		$userId = $this->filter('user_id', 'uint');
		if (!$userId)
		{
			$this->user = \XF::visitor();
		}
		else
		{
			$this->user = $this->assertRecordExists(User::class, $userId, null, 'requested_member_not_found');
		}

		return $this->user->user_id;
	}

	/**
	 * @param SessionActivity[] $activities
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('viewing_latest_content');
	}
}
