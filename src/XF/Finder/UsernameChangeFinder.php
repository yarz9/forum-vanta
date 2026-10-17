<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UsernameChange> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UsernameChange> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UsernameChange|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UsernameChange>
 */
class UsernameChangeFinder extends Finder
{
	public function visibleOnly()
	{
		$this->where('visible', 1);

		return $this;
	}

	public function recentOnly($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * $this->app()->options()->usernameChangeRecentLimit;
		}

		$this->where('change_date', '>=', $cutOff);

		return $this;
	}
}
