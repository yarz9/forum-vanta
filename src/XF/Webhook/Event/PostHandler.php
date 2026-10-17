<?php

namespace XF\Webhook\Event;

class PostHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		return ['Thread', 'Thread.Forum'];
	}
}
