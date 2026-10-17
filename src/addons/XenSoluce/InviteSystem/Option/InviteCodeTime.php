<?php

namespace XenSoluce\InviteSystem\Option;

use XF\Option\AbstractOption;

class InviteCodeTime extends AbstractOption
{
    public static function renderOptionInviteCodeTime(\XF\Entity\Option $option, array $htmlParams)
	{
	    if($option->option_value['Choose'] == 'permanent')
	    {
	        $cron = \XF::em()->getFinder('XF:CronEntry')->where('entry_id', 'xsDeleteExpiredCode')->fetchOne();
            $cron->active = 0;
            $cron->saveIfChanged($saved, false);
        }
	    elseif ($option->option_value['Choose'] == 'until')
        {
            $cron = \XF::em()->getFinder('XF:CronEntry')->where('entry_id', 'xsDeleteExpiredCode')->fetchOne();
            $cron->active = 1;
            $cron->saveIfChanged($saved, false);
        }
		return self::getTemplate('admin:option_template_xs_is_invitation_code_time', $option, $htmlParams);
    }
}