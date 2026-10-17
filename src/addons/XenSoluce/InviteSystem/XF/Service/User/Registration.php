<?php

namespace XenSoluce\InviteSystem\XF\Service\User;

class Registration extends XFCP_Registration
{

	public function setFromInput(array $input)
	{
	    parent::setFromInput($input);
	    $option = \XF::options();
	    $userGroupCode = $this->finder('XenSoluce\InviteSystem:UserGroupCode')
            ->where('code', '=', $input['code'])
            ->fetchOne();
	    $user = $this->user;
	    if(!empty($userGroupCode))
        {
            switch ($userGroupCode->type_user_group)
            {
                case 'all' :
                    $user->user_group_id = $userGroupCode->user_group;
                    $user->secondary_group_ids = $userGroupCode->secondary_user_group;
                    break;
                case 'first' :
                    $user->user_group_id = $userGroupCode->user_group;
                    break;
                case 'secondary' :
                    $user->secondary_group_ids = $userGroupCode->secondary_user_group;
                    break;
            }
            if($userGroupCode->max_invite > 1)
            {
                $userGroupCode->max_invite -= 1;
            }
            elseif($userGroupCode->max_invite != -1 && empty($userGroupCode->CodeCustom))
            {
                $userGroupCode->delete();
            }
        }
	    else
        {
            if($input['code'] && $option->xs_is_code_required['mandatory'] == 'no')
            {
                if($option->xs_is_code_required['first_group'])
                {
                    $user->user_group_id = $option->xs_is_code_required['first_user_group_id'];
                }
                if($option->xs_is_code_required['secondary_group'])
                {
                    $user->secondary_group_ids = $option->xs_is_code_required['secondary_user_group_id'];
                }
            }
        }

	}
}