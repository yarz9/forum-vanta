<?php

namespace XF\Install\Upgrade;

use XF\Service\Node\RebuildNestedSetService;

class Version2020970 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.2.9';
	}

	public function step1()
	{
		\XF::runOnce('nodeNestedSetRebuild', function ()
		{
			/** @var RebuildNestedSetService $service */
			$service = \XF::service(RebuildNestedSetService::class, 'XF:Node', [
				'parentField' => 'parent_node_id',
			]);
			$service->rebuildNestedSetInfo();
		});
	}
}
