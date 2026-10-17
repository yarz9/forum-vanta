<?php

namespace XF\Webhook\Event;

class ProfilePostHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		return ['ProfileUser', 'ProfileUser.Privacy'];
	}
}
