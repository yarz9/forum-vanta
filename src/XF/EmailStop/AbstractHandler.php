<?php

namespace XF\EmailStop;

use XF\Entity\User;

abstract class AbstractHandler
{
	protected $contentType;

	abstract public function getStopOneText(User $user, $contentId);
	abstract public function getStopAllText(User $user);
	abstract public function stopOne(User $user, $contentId);
	abstract public function stopAll(User $user);

	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}
}
