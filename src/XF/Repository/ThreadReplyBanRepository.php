<?php

namespace XF\Repository;

use XF\Entity\Thread;
use XF\Finder\ThreadReplyBanFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ThreadReplyBanRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findReplyBansForList()
	{
		$finder = $this->finder(ThreadReplyBanFinder::class);
		$finder->setDefaultOrder('ban_date', 'DESC')
			->with('Thread', true);
		return $finder;
	}

	/**
	 * @return Finder
	 */
	public function findReplyBansForThread(Thread $thread)
	{
		$finder = $this->findReplyBansForList();
		$finder->where('thread_id', $thread->thread_id)
			->with(['User', 'BannedBy']);
		return $finder;
	}

	public function cleanUpExpiredBans($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = time();
		}
		$this->db()->delete('xf_thread_reply_ban', 'expiry_date > 0 AND expiry_date < ?', $cutOff);
	}
}
