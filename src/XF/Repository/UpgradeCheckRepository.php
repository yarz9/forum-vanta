<?php

namespace XF\Repository;

use XF\Entity\UpgradeCheck;
use XF\Finder\UpgradeCheckFinder;
use XF\Mvc\Entity\Repository;

class UpgradeCheckRepository extends Repository
{
	public function canCheckForUpgrades(&$error = null)
	{
		return false;
	}

	public function canOneClickUpgrade(&$error = null)
	{
		if (!$this->app()->config('enableOneClickUpgrade'))
		{
			$error = \XF::phrase('one_click_upgrades_disabled_upgrade_manually');
			return false;
		}

		return false;
	}

	/**
	 * @return null|UpgradeCheck
	 */
	public function getLatestUpgradeCheck()
	{
		return $this->finder(UpgradeCheckFinder::class)
			->order('check_date', 'DESC')
			->fetchOne();
	}

	public function pruneUpgradeChecks($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 30;
		}

		return $this->db()->delete('xf_upgrade_check', 'check_date < ?', $cutOff);
	}
}
