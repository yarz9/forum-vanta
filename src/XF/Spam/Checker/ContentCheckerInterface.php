<?php

namespace XF\Spam\Checker;

use XF\Entity\User;

interface ContentCheckerInterface
{
	public function check(User $user, $message, array $extraParams = []);

	public function submitSpam($contentType, $contentIds);

	public function submitHam($contentType, $contentIds);
}
