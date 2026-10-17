<?php

namespace XF\Job;

use XF\Service\StyleProperty\RebuildService;

class StylePropertyRebuild extends AbstractJob
{
	protected $defaultData = [];

	public function run($maxRunTime)
	{
		/** @var RebuildService $rebuildService */
		$rebuildService = $this->app->service(RebuildService::class);

		$rebuildService->rebuildFullPropertyMap();
		$rebuildService->rebuildPropertyStyleCache();

		return $this->complete();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('style_properties');
		return sprintf('%s... %s', $actionPhrase, $typePhrase);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
