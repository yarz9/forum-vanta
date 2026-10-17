<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\ActivityLogRepository;
use XF\Repository\TrendingContentRepository;

class TrendingContent extends AbstractOption
{
	/**
	 * @param array<string, mixed> $htmlParams
	 */
	public static function renderWeightOptions(
		Option $option,
		array $htmlParams
	): string
	{
		$activityLogRepo = \XF::repository(ActivityLogRepository::class);
		$metrics = $activityLogRepo->getMetrics();

		return static::getTemplate(
			'admin:option_template_trendingContentWeights',
			$option,
			$htmlParams,
			[
				'metrics' => $metrics,
			]
		);
	}

	/**
	 * @param mixed $value
	 */
	public static function pruneResults(
		&$value,
		Option $option,
		string $optionId
	): bool
	{
		$trendingContentRepo = \XF::repository(TrendingContentRepository::class);
		$trendingContentRepo->pruneResults(\XF::$time);

		return true;
	}
}
