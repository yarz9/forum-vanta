<?php

namespace XF\Repository;

use XF\Entity\User;
use XF\Entity\UserConfirmation;
use XF\Mvc\Entity\Repository;

class UserConfirmationRepository extends Repository
{
	public function getConfirmationRecordOrDefault(User $user, $type)
	{
		$confirmation = $this->em->find(UserConfirmation::class, [$user->user_id, $type]);
		if (!$confirmation)
		{
			$confirmation = $this->em->create(UserConfirmation::class);
			$confirmation->user_id = $user->user_id;
			$confirmation->confirmation_type = $type;
		}

		return $confirmation;
	}

	public function cleanUpUserConfirmationRecords($cutOff = null)
	{
		$this->db()->delete('xf_user_confirmation', 'confirmation_date <= ?', $cutOff ?: time() - 3 * 86400);
	}

	public function fastDeleteUserConfirmationRecords(User $user, $type = null)
	{
		if ($type)
		{
			$this->db()->delete('xf_user_confirmation', 'user_id = ? AND confirmation_type = ?', [$user->user_id, $type]);
		}
		else
		{
			$this->db()->delete('xf_user_confirmation', 'user_id = ?', [$user->user_id]);
		}
	}
}
