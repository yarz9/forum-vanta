<?php

namespace XenSoluce\InviteSystem\Cron;

class InvitationBan
{
	public static function deleteExpiredBans()
	{
		\XF::app()->repository('XenSoluce\InviteSystem:Banning')->deleteExpiredInvitationBans();
	}
}