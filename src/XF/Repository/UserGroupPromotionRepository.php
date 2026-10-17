<?php

namespace XF\Repository;

use XF\Criteria\UserCriteria;
use XF\Entity\User;
use XF\Entity\UserGroupPromotion;
use XF\Entity\UserGroupPromotionLog;
use XF\Finder\UserGroupPromotionFinder;
use XF\Finder\UserGroupPromotionLogFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserGroupPromotionRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findUserGroupPromotionsForList()
	{
		return $this->finder(UserGroupPromotionFinder::class)->order('title');
	}

	/**
	 * @return UserGroupPromotion[]
	 */
	public function getActiveUserGroupPromotions()
	{
		return $this->finder(UserGroupPromotionFinder::class)->where('active', true)->fetch()->toArray();
	}

	/**
	 * @return Finder
	 */
	public function findUserGroupPromotionLogsForList()
	{
		return $this->finder(UserGroupPromotionLogFinder::class)->order(['promotion_date', 'user_id'], 'DESC');
	}

	public function getUserGroupPromotionLogsForUsers(array $userIds)
	{
		if (!$userIds)
		{
			return [];
		}

		$finder = $this->finder(UserGroupPromotionLogFinder::class)
			->where('user_id', $userIds)
			->order('promotion_date', 'desc');

		$logsGrouped = [];
		foreach ($finder->fetch() AS $log)
		{
			$logsGrouped[$log->user_id][$log->promotion_id] = $log;
		}

		return $logsGrouped;
	}

	public function getUserGroupPromotionLogsForUser($userId)
	{
		$logs = $this->getUserGroupPromotionLogsForUsers([$userId]);
		return $logs[$userId] ?? [];
	}

	public function getUserGroupPromotionTitlePairs()
	{
		return $this->findUserGroupPromotionsForList()
			->fetch()
			->pluckNamed('title', 'promotion_id');
	}

	/**
	 * @param User $user
	 * @param UserGroupPromotionLog[] $userGroupPromotionLogs
	 * @param UserGroupPromotion[] $userGroupPromotions
	 * @return int
	 */
	public function updatePromotionsForUser(User $user, $userGroupPromotionLogs = null, $userGroupPromotions = null)
	{
		if ($userGroupPromotionLogs === null)
		{
			$userGroupPromotionLogs = $this->getUserGroupPromotionLogsForUser($user->user_id);
		}

		if ($userGroupPromotions === null)
		{
			$userGroupPromotions = $this->getActiveUserGroupPromotions();
		}

		$changes = 0;

		foreach ($userGroupPromotions AS $userGroupPromotion)
		{
			if (isset($userGroupPromotionLogs[$userGroupPromotion->promotion_id]))
			{
				$skip = false;
				switch ($userGroupPromotionLogs[$userGroupPromotion->promotion_id]->promotion_state)
				{
					case 'manual': // has it, don't take it away
					case 'disabled': // never give it
						$skip = true;
				}
				if ($skip)
				{
					continue;
				}
				$hasPromotion = true;
			}
			else
			{
				$hasPromotion = false;
			}

			$userCriteria = $this->app()->criteria(UserCriteria::class, $userGroupPromotion->user_criteria);
			$userCriteria->setMatchOnEmpty(false);
			if ($userCriteria->isMatched($user))
			{
				if (!$hasPromotion)
				{
					$userGroupPromotion->promote($user);
					$changes++;
				}
			}
			else if ($hasPromotion)
			{
				$userGroupPromotion->demote($user);
				$changes++;
			}
		}

		return $changes;
	}
}
