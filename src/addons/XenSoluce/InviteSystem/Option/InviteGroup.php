<?php

namespace XenSoluce\InviteSystem\Option;

use XF\Option\AbstractOption;
use \XF\Entity\Option;
class InviteGroup extends AbstractOption
{

    public static function renderOptionInviteGroup(Option $option, array $htmlParams)
	{
	    $userGroupRepo = \XF::repository('XF:UserGroup');
		return self::getTemplate('admin:option_template_xs_is_invite_goup', $option, $htmlParams, [
		    'UserGroups' => $userGroupRepo->findUserGroupsForList()->fetch()
		]);
    }

}