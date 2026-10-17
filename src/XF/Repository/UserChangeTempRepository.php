<?php

namespace XF\Repository;

use XF\Entity\User;
use XF\Entity\UserChangeTemp;
use XF\Finder\UserChangeTempFinder;
use XF\Mvc\Entity\Repository;
use XF\Service\User\TempChangeService;

class UserChangeTempRepository extends Repository
{
	protected $validChangeRelations = ['Auth', 'Option', 'Profile', 'Privacy'];

	public function expireUserChangeByKey(User $user, $changeKey)
	{
		/** @var UserChangeTemp|null $change */
		$change = $this->em->findOne(UserChangeTemp::class, ['user_id' => $user->user_id, 'change_key' => $changeKey]);
		if ($change)
		{
			/** @var TempChangeService $changeService */
			$changeService = $this->app()->service(TempChangeService::class);
			return $changeService->expireChange($change);
		}

		return false;
	}

	public function removeExpiredChanges()
	{
		$expired = $this->finder(UserChangeTempFinder::class)
			->where('expiry_date', '<=', \XF::$time)
			->where('expiry_date', '!=', null)
			->order('expiry_date')
			->fetch(1000);

		/** @var TempChangeService $changeService */
		$changeService = $this->app()->service(TempChangeService::class);

		/** @var UserChangeTemp $change */
		foreach ($expired AS $change)
		{
			$changeService->expireChange($change);
		}
	}
}
