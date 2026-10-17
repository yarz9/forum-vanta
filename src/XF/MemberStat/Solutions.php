<?php

namespace XF\MemberStat;

use XF\Entity\MemberStat;

use function boolval;

class Solutions
{
	public static function isVisible(MemberStat $memberStat): bool
	{
		return boolval(\XF::db()->fetchOne("
			SELECT user_id
			FROM xf_user
			WHERE question_solution_count > 0
			LIMIT 1
		"));
	}
}
