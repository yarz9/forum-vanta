<?php

namespace XF\Spam\Checker;

use XF\Entity\User;

class Tornevall extends AbstractDnsBl implements UserCheckerInterface
{
	public function getType()
	{
		return 'Tornevall';
	}

	public function check(User $user, array $extraParams = [])
	{
		$block = $this->checkIp('%s.dnsbl.tornevall.org');
		$this->processDecision($block, true);
	}

	public function submit(User $user, array $extraParams = [])
	{
		return;
	}
}
