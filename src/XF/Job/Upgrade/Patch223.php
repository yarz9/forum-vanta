<?php

namespace XF\Job\Upgrade;

use XF\Entity\AddOn;
use XF\Job\AbstractJob;
use XF\Util\File;

class Patch223 extends AbstractJob
{
	public function run($maxRunTime)
	{
		$needsPatch = $this->app->db()->fetchOne("SELECT COUNT(*) FROM xf_upgrade_log WHERE version_id = ?", 2020370);

		if ((bool) $needsPatch)
		{
			/** @var AddOn $addOn */
			$addOn = \XF::em()->find(AddOn::class, 'XFRMSubmitter');
			if ($addOn)
			{
				$addOn->delete(false);
			}

			try
			{
				File::deleteDirectory(\XF::getAddOnDirectory() . \XF::$DS . 'XFRMSubmitter');
			}
			catch (\InvalidArgumentException $e)
			{
			}
		}

		return $this->complete();
	}

	public function getStatusMessage()
	{
		return 'Patching XenForo 2.2.3...';
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
