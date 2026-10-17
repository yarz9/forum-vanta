<?php

namespace XF\Repository;

use XF\Entity\UserTfaTrusted;
use XF\Finder\UserTfaTrustedFinder;
use XF\Mvc\Entity\Repository;

class UserTfaTrustedRepository extends Repository
{
	public function createTrustedKey($userId, $trustedUntil = null)
	{
		$userTrusted = $this->em->create(UserTfaTrusted::class);
		$userTrusted->user_id = $userId;
		if ($trustedUntil)
		{
			$userTrusted->trusted_until = $trustedUntil;
		}
		$userTrusted->save();

		return $userTrusted->trusted_key;
	}

	/**
	 * @param int $userId
	 * @param string $key
	 *
	 * @return UserTfaTrusted|null
	 */
	public function getTfaTrustRecord($userId, $key)
	{
		return $this->finder(UserTfaTrustedFinder::class)
			->where(['user_id' => $userId, 'trusted_key' => $key])
			->where('trusted_until', '>=', \XF::$time)
			->fetchOne();
	}

	public function hasOtherTrustedDevices($userId, $thisDeviceTrustKey = null)
	{
		$total = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_user_tfa_trusted
			WHERE user_id = ?
				" . ($thisDeviceTrustKey ? "AND trusted_key <> " . $this->db()->quote($thisDeviceTrustKey) : '') . "
		", $userId);

		return ($total > 0);
	}

	public function untrustDevice($userId, $trustKey)
	{
		if ($trustKey)
		{
			$this->db()->delete('xf_user_tfa_trusted', 'user_id = ? AND trusted_key = ?', [$userId, $trustKey]);
		}
	}

	public function untrustOtherDevices($userId, $thisDeviceTrustKey = null)
	{
		$this->db()->query("
			DELETE FROM xf_user_tfa_trusted
			WHERE user_id = ?
				" . ($thisDeviceTrustKey ? "AND trusted_key <> " . $this->db()->quote($thisDeviceTrustKey) : '') . "
		", $userId);
	}

	public function pruneTrustedKeys($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time;
		}

		return $this->db()->delete('xf_user_tfa_trusted', 'trusted_until < ?', $cutOff);
	}
}
