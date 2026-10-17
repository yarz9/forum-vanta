<?php

namespace XF\Repository;

use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Finder\PostFinder;
use XF\Mvc\Entity\Repository;

class PostRepository extends Repository
{
	public function findPostsForThreadView(Thread $thread, array $limits = [])
	{
		/** @var PostFinder $finder */
		$finder = $this->finder(PostFinder::class);
		$finder
			->inThread($thread, $limits)
			->orderByDate()
			->with('full');

		return $finder;
	}

	public function findSpecificPostsForThreadView(Thread $thread, array $postIds, array $limits = [])
	{
		/** @var PostFinder $finder */
		$finder = $this->finder(PostFinder::class);
		$finder
			->inThread($thread, $limits)
			->where('post_id', $postIds)
			->with('full');

		return $finder;
	}

	public function findNewestPostsInThread(Thread $thread, $newerThan, array $limits = [])
	{
		/** @var PostFinder $finder */
		$finder = $this->finder(PostFinder::class);
		$finder
			->inThread($thread, $limits)
			->order(['post_date', 'post_id'], 'DESC')
			->newerThan($newerThan);

		return $finder;
	}

	public function findNextPostsInThread(Thread $thread, $newerThan, array $limits = [])
	{
		/** @var PostFinder $finder */
		$finder = $this->finder(PostFinder::class);
		$finder
			->inThread($thread, $limits)
			->order(['post_date', 'post_id'], 'ASC')
			->newerThan($newerThan);

		return $finder;
	}

	public function sendModeratorActionAlert(Post $post, $action, $reason = '', array $extra = [])
	{
		if (!$post->user_id || !$post->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $post->Thread->title,
			'prefix_id' => $post->Thread->prefix_id,
			'link' => $this->app()->router('public')->buildLink('nopath:posts', $post),
			'threadLink' => $this->app()->router('public')->buildLink('nopath:threads', $post->Thread),
			'reason' => $reason,
		], $extra);

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$post->User,
			0,
			'',
			'user',
			$post->user_id,
			"post_{$action}",
			$extra
		);

		return true;
	}
}
