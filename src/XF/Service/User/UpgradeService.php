<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\User;
use XF\Entity\UserUpgrade;
use XF\Entity\UserUpgradeActive;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

use function intval, strlen;

class UpgradeService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	protected $purchaseRequestKey;

	/**
	 * @var UserUpgrade
	 */
	protected $userUpgrade;

	/**
	 * @var UserUpgradeActive
	 */
	protected $activeUpgrade;

	protected $ignoreUnpurchasable = false;

	protected $endDate;

	protected $extraData = [];

	protected $finalSetup = false;

	public function __construct(App $app, UserUpgrade $upgrade, User $user)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->setUpgrade($upgrade);
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setPurchaseRequestKey($purchaseRequestKey)
	{
		$this->purchaseRequestKey = $purchaseRequestKey;
	}

	protected function setUpgrade(UserUpgrade $upgrade)
	{
		$this->userUpgrade = $upgrade;
		$user = $this->user;

		$activeUpgrades = $upgrade->Active;
		$activeUpgrade = $activeUpgrades[$user->user_id];
		if (!$activeUpgrade)
		{
			$activeUpgrade = $this->em()->create(UserUpgradeActive::class);
			$activeUpgrade->user_upgrade_id = $upgrade->user_upgrade_id;
			$activeUpgrade->user_id = $user->user_id;
			$activeUpgrade->start_date = time();
		}
		$this->activeUpgrade = $activeUpgrade;
	}

	public function getUpgrade()
	{
		return $this->userUpgrade;
	}

	public function getActiveUpgrade()
	{
		return $this->activeUpgrade;
	}

	public function ignoreUnpurchasable($ignoreUnpurchsable)
	{
		$this->ignoreUnpurchasable = $ignoreUnpurchsable;
	}

	public function setEndDate($endDate)
	{
		$this->endDate = intval($endDate);
	}

	public function setExtraData(array $extraData)
	{
		$this->extraData = $extraData;
	}

	protected function finalSetup()
	{
		$this->finalSetup = true;

		$active = $this->activeUpgrade;
		$upgrade = $this->userUpgrade;

		$endDate = $this->endDate;

		if ($active->user_upgrade_record_id)
		{
			if ($endDate === null)
			{
				if ($active->end_date == 0 || !$active->extra['length_unit'])
				{
					$endDate = 0;
				}
				else
				{
					$endDate = min(
						2 ** 32 - 1,
						strtotime('+' . $active->extra['length_amount'] . ' ' . $active->extra['length_unit'], $active->end_date)
					);
				}
			}
		}
		else
		{
			if ($endDate === null)
			{
				if (!$upgrade->length_unit)
				{
					$endDate = 0;
				}
				else
				{
					$endDate = strtotime('+' . $upgrade->length_amount . ' ' . $upgrade->length_unit);
				}
			}

			$active->extra = array_merge([
				'cost_amount' => $upgrade->cost_amount,
				'cost_currency' => $upgrade->cost_currency,
				'length_amount' => $upgrade->length_amount,
				'length_unit' => $upgrade->length_unit,
			], $this->extraData);
		}

		$active->end_date = $endDate;

		if ($this->purchaseRequestKey)
		{
			$requestKey = $this->purchaseRequestKey;
			if (strlen($requestKey) > 32)
			{
				$requestKey = substr($requestKey, 0, 29) . '...';
			}

			$active->purchase_request_key = $requestKey;
		}
	}

	public function upgrade()
	{
		if (!$this->finalSetup)
		{
			$this->finalSetup();
		}

		$active = $this->activeUpgrade;
		$upgrade = $this->userUpgrade;
		$user = $this->user;

		// no need to check canPurchase -- if we have a payment, we should process the upgrade

		$db = $this->db();
		$db->beginTransaction();

		if (!$active->save(true, false))
		{
			$db->rollback();
			return false;
		}

		/** @var UserGroupChangeService $userGroupChange */
		$userGroupChange = $this->service(UserGroupChangeService::class);
		$userGroupChange->addUserGroupChange(
			$user->user_id,
			'userUpgrade-' . $upgrade->user_upgrade_id,
			$upgrade->extra_group_ids
		);

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertRepo->fastDeleteAlertsFromUser($user->user_id, 'user', $user->user_id, 'upgrade_end');

		$db->commit();

		return $active;
	}
}
