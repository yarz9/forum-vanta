<?php

namespace XF\MemberStat;

use XF\Entity\MemberStat;

class TrophyPoints
{
	public static function isVisible(MemberStat $memberStat): bool
	{
		return \XF::options()->enableTrophies;
	}
}
