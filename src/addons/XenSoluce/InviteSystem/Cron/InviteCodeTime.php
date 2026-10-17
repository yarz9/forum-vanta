<?php

namespace XenSoluce\InviteSystem\Cron;

class InviteCodeTime
{
	public static function deleteExpiredCode()
	{
		$options = \XF::options();
		if($options->xs_is_invitation_code_time['Choose'] == "until")
        {
            $condition = \XF::$time - 86400 * $options->xs_is_invitation_code_time['time'];
            $codes = \XF::em()->getFinder('XenSoluce\InviteSystem:CodeInvitation')->fetch();
            foreach ($codes as $code)
            {
                if($code->invitation_date < $condition && !$code->registered_user_id)
                {
                    $code->delete();
                }
            }
        }
	}
}