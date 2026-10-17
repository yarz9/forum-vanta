<?php

namespace XF\Cron;

use XF\Finder\UserFinder;
use XF\Repository\UserGroupPromotionRepository;

/**
 * Cron entry for executing user group promotions.
 */
class UserGroupPromotion
{
	/**
	 * Runs the cron-based check for new promotions that users should be awarded.
	 */
	public static function runPromotions()
	{
		/** @var UserGroupPromotionRepository $promotionRepo */
		$promotionRepo = \XF::repository(UserGroupPromotionRepository::class);

		$promotions = $promotionRepo->getActiveUserGroupPromotions();
		if (!$promotions)
		{
			return;
		}

		/** @var UserFinder $userFinder */
		$userFinder = \XF::app()->finder(UserFinder::class);
		$userFinder->where('last_activity', '>', time() - 2 * 3600)
			->with(['Profile', 'Option'])
			->order('user_id');

		$users = $userFinder->fetch();

		$userGroupPromotionLogs = $promotionRepo->getUserGroupPromotionLogsForUsers($users->keys());

		foreach ($users AS $user)
		{
			$promotionRepo->updatePromotionsForUser(
				$user,
				$userGroupPromotionLogs[$user->user_id] ?? [],
				$promotions
			);
		}
	}
}
