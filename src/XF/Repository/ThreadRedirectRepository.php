<?php

namespace XF\Repository;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Entity\ThreadRedirect;
use XF\Finder\ThreadRedirectFinder;
use XF\Mvc\Entity\Repository;

use function intval;

class ThreadRedirectRepository extends Repository
{
	public function createThreadRedirectionDouble(Thread $thread, Forum $target, $expiryLength = 0)
	{
		if ($thread->discussion_state != 'visible')
		{
			return null;
		}

		$data = $thread->toArray(false);
		$data = $this->cleanUpThreadRedirectionDouble($data);

		$data['discussion_type'] = 'redirect';
		$data['node_id'] = $target->node_id;

		/** @var Thread $double */
		$double = $this->em->create(Thread::class);
		$double->bulkSet($data);

		$this->createRedirectionRecordForThread($double, $thread, $expiryLength);

		if (!$double->save())
		{
			return null;
		}

		return $double;
	}

	protected function cleanUpThreadRedirectionDouble(array $data)
	{
		unset($data['thread_id'], $data['node_id']);

		if (!$data['first_post_reactions'])
		{
			unset($data['first_post_reactions']);
		}

		$data['first_post_id'] = 0;

		return $data;
	}

	public function createRedirectionRecordForThread(
		Thread $thread,
		Thread $targetThread,
		$expiryLength = 0,
		$nodeId = null,
		$saveNow = true
	)
	{
		$nodeId = intval($nodeId);
		if (!$nodeId)
		{
			$thread->node_id;
		}

		$saveNow = ($saveNow && $thread->thread_id > 0);

		/** @var ThreadRedirect $redirect */
		$redirect = $thread->getRelationOrDefault('Redirect', $saveNow ? false : true);
		$redirect->target_url = $this->app()->router('public')->buildLink('nopath:threads', $targetThread);
		$redirect->redirect_key = "thread-{$targetThread->thread_id}-{$thread->node_id}-";
		$redirect->expiry_date = $expiryLength ? \XF::$time + $expiryLength : 0;

		if ($saveNow)
		{
			$redirect->save();
		}

		return $redirect;
	}

	public function deleteRedirectsByKey($key)
	{
		$redirects = $this->finder(ThreadRedirectFinder::class)
			->where('redirect_key', 'like', $key)
			->with('Thread')
			->fetch();

		$db = $this->db();
		$db->beginTransaction();

		foreach ($redirects AS $redirect)
		{
			$this->deleteRedirect($redirect, false, false);
		}

		$db->commit();

		return $redirects;
	}

	protected function deleteRedirect(ThreadRedirect $redirect, $throw = true, $newTransaction = true)
	{
		if ($redirect->Thread)
		{
			$redirect->Thread->delete($throw, $newTransaction);
		}
		else
		{
			$redirect->delete($throw, $newTransaction);
		}
	}

	public function deleteRedirectsToThread(Thread $thread)
	{
		$key = 'thread-' . $thread->thread_id . '-%';
		return $this->deleteRedirectsByKey($key);
	}

	public function deleteRedirectsToThreadInForum(Thread $thread, Forum $forum)
	{
		$key = 'thread-' . $thread->thread_id . '-' . $forum->node_id . '-';
		return $this->deleteRedirectsByKey($key);
	}

	public function rebuildThreadRedirectKey(Thread $thread)
	{
		/** @var ThreadRedirect $redirect */
		$redirect = $thread->Redirect;
		if ($redirect)
		{
			$key = preg_replace('/^(thread-\d+)-\d+-$/', '$1-' . $thread->node_id . '-', $redirect->redirect_key);
			if ($key != $redirect->redirect_key)
			{
				$redirect->redirect_key = $key;
				$redirect->save();
			}
		}
	}

	public function pruneThreadRedirects($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time;
		}

		$redirects = $this->finder(ThreadRedirectFinder::class)
			->where('expiry_date', '>', 0)
			->where('expiry_date', '<=', $cutOff)
			->with('Thread')
			->fetch();

		$db = $this->db();
		$db->beginTransaction();

		foreach ($redirects AS $redirect)
		{
			$this->deleteRedirect($redirect, false, false);
		}

		$db->commit();
	}
}
