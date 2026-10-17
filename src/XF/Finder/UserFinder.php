<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

use function intval;

/**
 * @method AbstractCollection<\XF\Entity\User> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\User> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\User|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\User>
 */
class UserFinder extends Finder
{
	public function isBirthday($privacyCheck = true)
	{
		[$month, $day] = explode('/', \XF::language()->date(time(), 'n/j'));

		$this->with('Profile', true);

		$this->where('Profile.dob_day', intval($day));
		$this->where('Profile.dob_month', intval($month));

		if ($privacyCheck)
		{
			$this->with('Option', true);
			$this->where('Option.show_dob_date', true);
		}

		return $this;
	}

	public function isValidUser($recentlyActive = false)
	{
		$this->where('is_banned', false);
		$this->where('user_state', 'valid');
		if ($recentlyActive)
		{
			$this->isRecentlyActive();
		}
		return $this;
	}

	public function isRecentlyActive($days = 180)
	{
		$this->where('last_activity', '>', time() - ($days * 86400));
		return $this;
	}
}
