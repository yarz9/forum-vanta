<?php

namespace XF\Install\Upgrade;

use XF\Repository\NavigationRepository;

class Version2030036 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 6';
	}

	public function step1(): void
	{
		// the deprecated template fn method has been removed
		// but navigation entries may still have stale cached code
		$navigationRepo = \XF::repository(NavigationRepository::class);
		$navigationRepo->rebuildNavigationEntries();
	}
}
