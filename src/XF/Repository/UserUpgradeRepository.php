<?php

namespace XF\Repository;

use XF\Data\Currency;
use XF\Entity\UserUpgrade;
use XF\Entity\UserUpgradeActive;
use XF\Entity\UserUpgradeExpired;
use XF\Finder\UserUpgradeActiveFinder;
use XF\Finder\UserUpgradeExpiredFinder;
use XF\Finder\UserUpgradeFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Service\User\DowngradeService;
use XF\Service\User\UserGroupChangeService;

class UserUpgradeRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findUserUpgradesForList()
	{
		return $this->finder(UserUpgradeFinder::class)
			->setDefaultOrder('display_order');
	}

	/**
	 * @return Finder
	 */
	public function findActiveUserUpgradesForList()
	{
		$finder = $this->finder(UserUpgradeActiveFinder::class);

		$finder
			->with(['User', 'PurchaseRequest.PaymentProfile'])
			->with('Upgrade', true)
			->setDefaultOrder('start_date', 'DESC');

		return $finder;
	}

	/**
	 * @return Finder
	 */
	public function findExpiredUserUpgradesForList()
	{
		$finder = $this->finder(UserUpgradeExpiredFinder::class);

		$finder
			->with(['User', 'PurchaseRequest.PaymentProfile'])
			->with('Upgrade', true)
			->setDefaultOrder('end_date', 'DESC');

		return $finder;
	}

	public function getFilteredUserUpgradesForList()
	{
		$visitor = \XF::visitor();

		$finder = $this->findUserUpgradesForList()
			->with(
				'Active|'
				. $visitor->user_id
				. '.PurchaseRequest'
			);

		$purchased = [];
		$upgrades = $finder->fetch();

		if ($visitor->user_id && $upgrades->count())
		{
			/** @var UserUpgrade $upgrade */
			foreach ($upgrades AS $upgradeId => $upgrade)
			{
				if (isset($upgrade->Active[$visitor->user_id]))
				{
					// purchased
					$purchased[$upgradeId] = $upgrade;
					unset($upgrades[$upgradeId]); // can't buy again

					// remove any upgrades disabled by this
					foreach ($upgrade['disabled_upgrade_ids'] AS $disabledId)
					{
						unset($upgrades[$disabledId]);
					}
				}
				else if (!$upgrade->canPurchase())
				{
					unset($upgrades[$upgradeId]);
				}
			}
		}

		return [$upgrades, $purchased];
	}

	public function getUpgradeTitlePairs()
	{
		return $this->findUserUpgradesForList()
			->fetch()
			->pluckNamed('title', 'user_upgrade_id');
	}

	public function getUserUpgradeCount()
	{
		return $this->finder(UserUpgradeFinder::class)
			->where('can_purchase', 1)
			->total();
	}

	public function rebuildUpgradeCount()
	{
		$cache = $this->getUserUpgradeCount();
		\XF::registry()->set('userUpgradeCount', $cache);
		return $cache;
	}

	public function downgradeExpiredUpgrades()
	{
		/** @var UserUpgradeActive[] $expired */
		$expired = $this->finder(UserUpgradeActiveFinder::class)
			->with('Upgrade')
			->with('User')
			->where('end_date', '<', \XF::$time)
			->where('end_date', '>', 0)
			->order('end_date')
			->fetch(1000);

		$db = $this->db();
		$db->beginTransaction();

		foreach ($expired AS $active)
		{
			$upgrade = $active->Upgrade;

			if ($upgrade && $upgrade->recurring)
			{
				// For recurring payments give a 24 hour grace period
				if ($active->end_date + 86400 >= \XF::$time)
				{
					continue;
				}
			}

			if ($upgrade)
			{
				/** @var DowngradeService $downgradeService */
				$downgradeService = $this->app()->service(DowngradeService::class, $active->Upgrade, $active->User, $active);
				$downgradeService->downgrade();
			}
			else
			{
				/** @var UserGroupChangeService $userGroupChange */
				$userGroupChange = $this->app()->service(UserGroupChangeService::class);
				$userGroupChange->removeUserGroupChange(
					$active->user_id,
					'userUpgrade-' . $active->user_upgrade_id
				);

				$this->expireActiveUpgrade($active);
			}
		}

		$db->commit();
	}

	public function expireActiveUpgrade(UserUpgradeActive $active, ?UserUpgradeExpired $expired = null)
	{
		if ($expired === null)
		{
			$expired = $this->em->create(UserUpgradeExpired::class);
		}

		$expired->user_upgrade_record_id = $active->user_upgrade_record_id;
		$expired->user_id = $active->user_id;
		$expired->purchase_request_key = $active->purchase_request_key;
		$expired->user_upgrade_id = $active->user_upgrade_id;
		$expired->extra = $active->extra;
		$expired->start_date = $active->start_date;
		$expired->end_date = time();
		$expired->original_end_date = $active->end_date;

		// There's an edge case where this can fail if the user_upgrade_record_id is already used.
		// There's code that should prevent it from happening, but we need to just ignore that situation.
		$expired->save(false, false);

		$active->delete(true, false);
	}

	public function getCostPhraseForUserUpgrade(
		UserUpgrade $userUpgrade,
		$costAmount = null,
		$costCurrency = null
	)
	{
		$costAmount = $costAmount ?? $userUpgrade->cost_amount;
		$costCurrency = $costCurrency ?? $userUpgrade->cost_currency;

		$cost = $this->app()->data(Currency::class)->languageFormat($costAmount, $costCurrency);
		$phrase = $cost;

		if ($userUpgrade->length_unit)
		{
			if ($userUpgrade->length_amount > 1)
			{
				if ($userUpgrade->recurring)
				{
					$phrase = \XF::phrase("x_per_y_{$userUpgrade->length_unit}s", [
						'cost' => $cost,
						'length' => $userUpgrade->length_amount,
					]);
				}
				else
				{
					$phrase = \XF::phrase("x_for_y_{$userUpgrade->length_unit}s", [
						'cost' => $cost,
						'length' => $userUpgrade->length_amount,
					]);
				}
			}
			else
			{
				if ($userUpgrade->recurring)
				{
					$phrase = \XF::phrase("x_per_{$userUpgrade->length_unit}", [
						'cost' => $cost,
					]);
				}
				else
				{
					$phrase = \XF::phrase("x_for_one_{$userUpgrade->length_unit}", [
						'cost' => $cost,
					]);
				}
			}
		}

		return $phrase;
	}
}
