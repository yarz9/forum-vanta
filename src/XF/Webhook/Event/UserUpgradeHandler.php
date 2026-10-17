<?php

namespace XF\Webhook\Event;

class UserUpgradeHandler extends AbstractHandler
{
	public function getEvents(): array
	{
		return array_merge(parent::getEvents(), [
			'purchase_complete', 'purchase_reinstate', 'purchase_reverse',
		]);
	}
}
