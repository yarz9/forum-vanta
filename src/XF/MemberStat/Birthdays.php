<?php

namespace XF\MemberStat;

use XF\Entity\MemberStat;
use XF\Entity\User;
use XF\Finder\UserFinder;

class Birthdays
{
	public static function getBirthdayUsers(MemberStat $memberStat, UserFinder $finder)
	{
		$finder
			->isBirthday()
			->isRecentlyActive(365)
			->isValidUser();

		$users = $finder->fetch($memberStat->user_limit * 3);

		$results = $users->pluck(function (User $user)
		{
			return [$user->user_id, \XF::language()->numberFormat($user->Profile->getAge())];
		});

		return $results;
	}
}
