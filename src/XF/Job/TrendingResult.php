<?php

namespace XF\Job;

use XF\Repository\TrendingContentRepository;

class TrendingResult extends AbstractJob
{
	/**
	 * @var array<string, mixed>
	 */
	protected $defaultData = [
		'order' => null,
		'duration' => null,
		'content_type' => '',
		'content_container_id' => 0,
	];

	/**
	 * @param float $maxRunTime
	 */
	public function run($maxRunTime): JobResult
	{
		if (!$this->data['order'] || !$this->data['duration'])
		{
			return $this->complete();
		}

		$trendingContentRepo = $this->app->repository(TrendingContentRepository::class);
		$trendingContentRepo->createResult(
			$this->data['order'],
			$this->data['duration'],
			$this->data['content_type'],
			$this->data['content_container_id']
		);

		return $this->complete();
	}

	public function getStatusMessage(): string
	{
		return \XF::phrase('computing_trending_result');
	}

	public function canCancel(): bool
	{
		return false;
	}

	public function canTriggerByChoice(): bool
	{
		return false;
	}
}
