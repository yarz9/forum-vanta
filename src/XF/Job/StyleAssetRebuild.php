<?php

namespace XF\Job;

use XF\Service\Style\AssetRebuildService;

class StyleAssetRebuild extends AbstractJob
{
	public function run($maxRunTime)
	{
		/** @var AssetRebuildService $rebuildService */
		$rebuildService = $this->app->service(AssetRebuildService::class);

		$rebuildService->rebuildAssetStyleCache();

		return $this->complete();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('style_assets');
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
