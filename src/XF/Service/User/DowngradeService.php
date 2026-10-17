<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\User;
use XF\Entity\UserUpgrade;
use XF\Entity\UserUpgradeActive;
use XF\Entity\UserUpgradeExpired;
use XF\Repository\UserAlertRepository;
use XF\Repository\UserUpgradeRepository;
use XF\Service\AbstractService;

class DowngradeService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var UserUpgrade
	 */
	protected $userUpgrade;

	/**
	 * @var UserUpgradeActive
	 */
	protected $activeUpgrade;

	/**
	 * @var UserUpgradeExpired
	 */
	protected $expiredUpgrade;

	protected $sendAlert = true;

	public function __construct(App $app, UserUpgrade $upgrade, User $user, ?UserUpgradeActive $active = null)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->activeUpgrade = $active;
		$this->setUpgrade($upgrade);
	}

	public function getUser()
	{
		return $this->user;
	}

	protected function setUpgrade(UserUpgrade $upgrade)
	{
		$this->userUpgrade = $upgrade;
		$user = $this->user;

		if (!$this->activeUpgrade)
		{
			$activeUpgrades = $upgrade->Active;
			$this->activeUpgrade = $activeUpgrades[$user->user_id] ?? null;
		}

		$this->expiredUpgrade = $this->em()->create(UserUpgradeExpired::class);
	}

	public function getUpgrade()
	{
		return $this->userUpgrade;
	}

	public function getActiveUpgrade()
	{
		return $this->activeUpgrade;
	}

	public function getExpiredUpgrade()
	{
		return $this->expiredUpgrade;
	}

	public function setSendAlert($sendAlert)
	{
		$this->sendAlert = $sendAlert;
	}

	public function downgrade()
	{
		$user = $this->user;
		$upgrade = $this->userUpgrade;
		$active = $this->activeUpgrade;
		$expired = $this->expiredUpgrade;

		$db = $this->db();
		$db->beginTransaction();

		/** @var UserGroupChangeService $userGroupChange */
		$userGroupChange = $this->service(UserGroupChangeService::class);
		$userGroupChange->removeUserGroupChange(
			$user->user_id,
			'userUpgrade-' . $upgrade->user_upgrade_id
		);

		if ($active)
		{
			/** @var UserUpgradeRepository $upgradeRepo */
			$upgradeRepo = $this->repository(UserUpgradeRepository::class);
			$upgradeRepo->expireActiveUpgrade($active, $expired);

			if (!$upgrade->recurring && $upgrade->can_purchase && $this->sendAlert)
			{
				/** @var UserAlertRepository $alertRepo */
				$alertRepo = $this->app->repository(UserAlertRepository::class);
				$alertRepo->alert(
					$user,
					$user->user_id,
					$user->username,
					'user',
					$user->user_id,
					'upgrade_end',
					[]
				);
			}
		}

		$db->commit();

		return true;
	}
}
