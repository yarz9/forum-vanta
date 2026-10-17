<?php

namespace XF\Job;

use XF\Repository\IconRepository;
use XF\Service\Icon\UsageAnalyzerService;

class IconUsage extends AbstractJob
{
	/**
	 * @var mixed[]
	 */
	protected $defaultData = [
		'content_type' => null,

		'current_step' => 0,
		'last_offset' => null,
	];

	/**
	 * @param float $maxRunTime
	 */
	public function run($maxRunTime): JobResult
	{
		$iconRepo = $this->app->repository(IconRepository::class);

		if (
			$this->data['current_step'] === 0 &&
			$this->data['last_offset'] === null
		)
		{
			$iconRepo->purgeUsageRecords($this->data['content_type']);
		}


		$analyzer = $this->app->service(
			UsageAnalyzerService::class,
			$this->data['content_type']
		);
		$analyzer->restoreState(
			$this->data['current_step'],
			$this->data['last_offset']
		);

		$result = $analyzer->analyze($maxRunTime);
		$iconRepo->recordUsage($analyzer->getIcons());

		if (!$result->isCompleted())
		{
			$continueData = $result->getContinueData();
			$this->data['current_step'] = $continueData['currentStep'];
			$this->data['last_offset'] = $continueData['lastOffset'];

			return $this->resume();
		}

		$iconRepo->runSpriteGenerator();
		return $this->complete();
	}

	public function getStatusMessage(): string
	{
		return \XF::phrase('analyzing_icon_usage...');
	}

	public function canCancel(): bool
	{
		return false;
	}

	public function canTriggerByChoice(): bool
	{
		return true;
	}
}
