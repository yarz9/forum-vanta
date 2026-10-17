<?php

namespace XF\Job;

use XF\Entity\User;
use XF\Service\User\ProfileBannerService;

class ProfileBannerOptimization extends AbstractImageOptimizationJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user_profile
				WHERE user_id > ?
				    AND banner_date > 0
					AND banner_optimized = 0
				ORDER BY user_id
			",
			$batch
		), $start);
	}

	protected function optimizeById($id): void
	{
		$user = $this->app->em()->find(User::class, $id);

		$bannerService = $this->app->service(ProfileBannerService::class, $user);
		$bannerService->silentRunning(true);
		$bannerService->logIp(false);
		$bannerService->optimizeExistingBanner();
	}

	protected function getStatusType(): string
	{
		return \XF::phrase('profile_banners');
	}
}
