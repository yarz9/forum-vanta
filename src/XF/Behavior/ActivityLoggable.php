<?php

namespace XF\Behavior;

use XF\Mvc\Entity\Behavior;
use XF\Repository\ActivityLogRepository;

class ActivityLoggable extends Behavior
{
	protected function getDefaultOptions(): array
	{
		return [
			'enabled' => true,
		];
	}

	public function postSave(): void
	{
		if (!$this->options['enabled'])
		{
			return;
		}

		$this->getActivityLogRepo()->updateContainerId($this->entity);
	}

	public function postDelete(): void
	{
		if (!$this->options['enabled'])
		{
			return;
		}

		$this->getActivityLogRepo()->removeLogs($this->entity);
	}

	protected function getActivityLogRepo(): ActivityLogRepository
	{
		return $this->repository(ActivityLogRepository::class);
	}
}
