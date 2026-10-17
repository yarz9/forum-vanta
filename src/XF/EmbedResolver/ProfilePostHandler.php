<?php

namespace XF\EmbedResolver;

class ProfilePostHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		return ['User', 'ProfileUser', 'ProfileUser.Privacy'];
	}
}
