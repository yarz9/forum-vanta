<?php

namespace XF\Spam\Checker;

use XF\Entity\User;
use XF\Finder\IpFinder;
use XF\Util\Ip;

class RejectedUsers extends AbstractProvider implements UserCheckerInterface
{
	protected function getType()
	{
		return 'RejectedUsers';
	}

	public function check(User $user, array $extraParams = [])
	{
		$option = $this->app()->options()->approveSharedBannedRejectedIp;

		$ip = Ip::stringToBinary(
			$this->app()->request()->getIp(),
			false
		);
		if ($ip === false)
		{
			$this->logDecision('allowed');
			return;
		}

		$ipFinder = $this->app()->finder(IpFinder::class)
			->with('User', true)
			->where('User.user_state', 'rejected')
			->where('ip', $ip)
			->order('log_date', 'DESC')
			->pluckFrom('User', 'user_id');

		if ($option['days'])
		{
			$ipFinder->where('log_date', '>', (time() - $option['days'] * 86400));
		}

		$bannedNames = $ipFinder->fetch()->pluckNamed('username');
		if ($bannedNames)
		{
			$this->logDetail('shared_ip_rejected_user_x', [
				'users' => implode(', ', $bannedNames),
			]);
			$this->logDecision('moderated');
			return;
		}

		$this->logDecision('allowed');
		return;
	}

	public function submit(User $user, array $extraParams = [])
	{
		return;
	}
}
