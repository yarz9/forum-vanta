<?php

namespace XF\Repository;

use XF\Entity\TfaAttempt;
use XF\Finder\TfaAttemptFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class TfaAttemptRepository extends Repository
{
	public function logFailedTfaAttempt($userId)
	{
		$loginAttempt = $this->em->create(TfaAttempt::class);
		$loginAttempt->bulkSet([
			'user_id' => $userId,
			'attempt_date' => time(),
		]);
		$loginAttempt->save();
	}

	public function countTfaAttemptsSince($cutOff, $userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_tfa_attempt
			WHERE attempt_date >= ?
				AND user_id = ?
		", [$cutOff, $userId]);
	}

	public function clearTfaAttempts($userId)
	{
		/** @var Finder $finder */
		$finder = $this->finder(TfaAttemptFinder::class);

		$attempts = $finder->where('user_id', $userId)
			->fetch();

		foreach ($attempts AS $attempt)
		{
			$attempt->delete();
		}
	}

	public function cleanUpTfaAttempts()
	{
		$this->db()->delete('xf_tfa_attempt', 'attempt_date < ?', time() - 86400);
	}
}
