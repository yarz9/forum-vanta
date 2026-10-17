<?php

namespace XF\Repository;

use XF\Criteria\UserCriteria;
use XF\Entity\Trophy;
use XF\Entity\User;
use XF\Entity\UserTrophy;
use XF\Finder\TrophyFinder;
use XF\Finder\UserTrophyFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

use function intval;

class TrophyRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findTrophiesForList()
	{
		return $this->finder(TrophyFinder::class)->order('trophy_points');
	}

	/**
	 * @return Finder
	 */
	public function findUsersTrophies(array $userIds)
	{
		return $this->finder(UserTrophyFinder::class)
			->where('user_id', $userIds)
			->setDefaultOrder(['user_id', 'award_date']);
	}

	/**
	 * @param integer $userId
	 * @return Finder
	 */
	public function findUserTrophies($userId)
	{
		return $this->finder(UserTrophyFinder::class)
			->where('user_id', $userId)
			->setDefaultOrder('award_date', 'DESC');
	}

	/**
	 * @param User $user
	 * @param UserTrophy[] $userTrophies
	 * @param Trophy[] $trophies
	 * @return int
	 */
	public function updateTrophiesForUser(User $user, $userTrophies = null, $trophies = null)
	{
		if ($userTrophies === null)
		{
			$userTrophies = $this->findUserTrophies($user->user_id)->fetch();
		}

		if ($trophies === null)
		{
			$trophies = $this->findTrophiesForList()->fetch();
		}

		$awarded = 0;

		foreach ($trophies AS $trophy)
		{
			if (isset($userTrophies[$user->user_id . '-' . $trophy->trophy_id]))
			{
				continue;
			}

			$userCriteria = $this->app()->criteria(UserCriteria::class, $trophy->user_criteria);
			$userCriteria->setMatchOnEmpty(false);
			if ($userCriteria->isMatched($user))
			{
				$this->awardTrophyToUser($trophy, $user);
			}
		}

		return $awarded;
	}

	public function awardTrophyToUser(Trophy $trophy, User $user)
	{
		$inserted = $this->db()->insert('xf_user_trophy', [
			'user_id' => $user->user_id,
			'trophy_id' => $trophy->trophy_id,
			'award_date' => \XF::$time,
		], false, false, 'IGNORE');

		if ($inserted)
		{
			$user->fastUpdate('trophy_points', $user->trophy_points + $trophy->trophy_points);

			/** @var UserAlertRepository $alertRepo */
			$alertRepo = $this->repository(UserAlertRepository::class);
			$alertRepo->alertFromUser(
				$user,
				$user,
				'trophy',
				$trophy->trophy_id,
				'award'
			);

			return true;
		}
		else
		{
			return false;
		}
	}

	public function recalculateUserTrophyPoints(User $user)
	{
		$points = intval($this->db()->fetchOne("
			SELECT SUM(trophy.trophy_points)
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (user_trophy.trophy_id = trophy.trophy_id)
			WHERE user_trophy.user_id = ?
		", $user->user_id));

		$user->fastUpdate('trophy_points', $points);

		return $points;
	}
}
