<?php

namespace XF\Job;

use XF\Util\File;

abstract class AbstractImageOptimizationJob extends AbstractRebuildJob
{
	abstract protected function optimizeById($id): void;

	public function run($maxRunTime): JobResult
	{
		if ($this->app->options()->imageOptimization !== 'optimize')
		{
			return $this->complete();
		}

		$result = parent::run($maxRunTime);

		File::cleanUpTempFiles();

		return $result;
	}

	protected function rebuildById($id): void
	{
		$this->optimizeById($id);
	}

	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('optimizing');
		$typePhrase = $this->getStatusType();
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
	}
}
