<?php

namespace XF\Widget;

use XF\Repository\SessionActivityRepository;

class OnlineStatistics extends AbstractWidget
{
	public function render()
	{
		/** @var SessionActivityRepository $activityRepo */
		$activityRepo = $this->repository(SessionActivityRepository::class);

		$viewParams = [
			'counts' => $activityRepo->getOnlineCounts(),
		];
		return $this->renderer('widget_online_statistics', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return null;
	}
}
