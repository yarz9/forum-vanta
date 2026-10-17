<?php

namespace XF\Import\DataHelper;

use XF\Repository\UserFollowRepository;
use XF\Repository\UserIgnoredRepository;

use function array_slice;

class User extends AbstractHelper
{
	public function importFollowing($userId, array $followUserIds)
	{
		if (!$followUserIds)
		{
			return;
		}

		$followUserIds = array_slice($followUserIds, 0, 1000);

		$insert = [];
		foreach ($followUserIds AS $followUserId)
		{
			$insert[] = [
				'user_id' => $userId,
				'follow_user_id' => $followUserId,
				'follow_date' => \XF::$time,
			];
		}

		if ($insert)
		{
			$this->db()->insertBulk('xf_user_follow', $insert, false, false, 'IGNORE');
			$this->em()->getRepository(UserFollowRepository::class)->rebuildFollowingCache($userId);
		}
	}

	public function importIgnored($userId, array $ignoredUserIds)
	{
		if (!$ignoredUserIds)
		{
			return;
		}

		$ignoredUserIds = array_slice($ignoredUserIds, 0, 1000);

		$insert = [];
		foreach ($ignoredUserIds AS $ignoredUserId)
		{
			$insert[] = [
				'user_id' => $userId,
				'ignored_user_id' => $ignoredUserId,
			];
		}

		if ($insert)
		{
			$this->db()->insertBulk('xf_user_ignored', $insert, false, false, 'IGNORE');
			$this->em()->getRepository(UserIgnoredRepository::class)->rebuildIgnoredCache($userId);
		}
	}
}
