<?php

namespace XF\Spam\Checker;

use XF\Entity\User;

interface UserCheckerInterface
{
	public function check(User $user, array $extraParams = []);

	public function submit(User $user, array $extraParams = []);
}
