<?php

namespace XF\Repository;

use XF\Finder\FeedFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class FeedRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findFeedsForList()
	{
		return $this->finder(FeedFinder::class)->order('title');
	}

	/**
	 * @return Finder
	 */
	public function findDueFeeds($time = null)
	{
		/** @var FeedFinder $finder */
		$finder = $this->finder(FeedFinder::class);

		return $finder
			->isDue($time)
			->where('active', true)
			->with(['Forum', 'Forum.Node'], true)
			->order('last_fetch');
	}
}
