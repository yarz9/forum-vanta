<?php

namespace XF\NewsFeed;

/**
 * @extends AbstractHandler<\XF\Entity\ProfilePost>
 */
class ProfilePostHandler extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['ProfileUser', 'ProfileUser.Privacy'];
	}
}
