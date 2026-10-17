<?php

namespace XF\Repository;

use XF\Finder\MemberStatFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class MemberStatRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findMemberStatsForList()
	{
		return $this->finder(MemberStatFinder::class)
			->order('display_order');
	}

	/**
	 * @return Finder
	 */
	public function findMemberStatsForDisplay()
	{
		/** @var MemberStatFinder $finder */
		$finder = $this->finder(MemberStatFinder::class);

		$finder
			->activeOnly()
			->order('display_order')
			->keyedBy('member_stat_key');

		return $finder;
	}

	/**
	 * @return Finder
	 */
	public function findCacheableMemberStats()
	{
		/** @var MemberStatFinder $finder */
		$finder = $this->finder(MemberStatFinder::class);

		$finder
			->activeOnly()
			->cacheableOnly()
			->order('member_stat_id');

		return $finder;
	}

	public function emptyCache($memberStatKey)
	{
		/** @var MemberStatFinder $finder */
		$finder = $this->finder(MemberStatFinder::class);

		$memberStat = $finder
			->cacheableOnly()
			->where('member_stat_key', $memberStatKey)
			->order('member_stat_id')
			->fetchOne();

		if (!$memberStat)
		{
			return false;
		}

		$memberStat->cache_results = null;
		$memberStat->cache_expiry = 0;
		$memberStat->save();
		return true;
	}
}
