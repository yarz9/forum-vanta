<?php

namespace XF\Job;

use XF\Entity\User;
use XF\Service\User\AvatarService;

class AvatarOptimization extends AbstractImageOptimizationJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				    AND avatar_date > 0
					AND avatar_optimized = 0
				ORDER BY user_id
			",
			$batch
		), $start);
	}

	protected function optimizeById($id): void
	{
		$user = $this->app->em()->find(User::class, $id);

		$avatarService = $this->app->service(AvatarService::class, $user);
		$avatarService->silentRunning(true);
		$avatarService->logIp(false);
		$avatarService->optimizeExistingAvatar();
	}

	protected function getStatusType(): string
	{
		return \XF::phrase('avatars');
	}
}
