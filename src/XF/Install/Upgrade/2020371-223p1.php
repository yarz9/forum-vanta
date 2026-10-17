<?php

namespace XF\Install\Upgrade;

use XF\Job\Upgrade\Patch223;

class Version2020371 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.2.3 Patch 1';
	}

	public function step1()
	{
		$this->insertUpgradeJob('upgradePatch223', Patch223::class, []);
	}
}
