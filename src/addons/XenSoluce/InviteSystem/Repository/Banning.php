<?php

namespace XenSoluce\InviteSystem\Repository;

use XF\Mvc\Entity\Repository;

class Banning extends Repository
{
    public function findInvitationbanUserForList()
    {
        return $this->finder('XenSoluce\InviteSystem:Banning')
            ->setDefaultOrder([['ban_date', 'DESC'], ['User.username']]);
    }

	public function InvitationbanUser(\XF\Entity\User $user, $endDate, $reason, &$error = null, \XF\Entity\User $banBy = null)
	{
		if ($endDate < time() && $endDate !== 0)
		{
			$error = \XF::phraseDeferred('please_enter_a_date_in_the_future');
			return false;
		}

		$banBy = $banBy ?: \XF::visitor();
		$userBan = $user->getRelationOrDefault('InviteBan', false);
		if ($userBan->isInsert())
		{
			$userBan->ban_user_id = $banBy->user_id;
		}

		$userBan->end_date = $endDate;

		$userBan->ban_reason = $reason;

		if (!$userBan->preSave())
		{
			$errors = $userBan->getErrors();
			$error = reset($errors);
			return false;
		}

		try
		{
			$userBan->save(false);
		}
		catch (\XF\Db\Exception $e) {}

		return true;
	}

    public function deleteExpiredInvitationBans($cutOff = null)
    {
        foreach ($this->findExpiredInvitationBans($cutOff)->fetch() AS $ban)
        {
            $ban->delete();
        }
    }

    public function findExpiredInvitationBans($cutOff = null)
    {
        if ($cutOff === null)
        {
            $cutOff = time();
        }

        return $this->finder('XenSoluce\InviteSystem:Banning')
            ->where('end_date', '>', 0)
            ->where('end_date', '<=', $cutOff);
    }
}