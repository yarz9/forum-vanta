<?php

namespace XF\NewsFeed;

/**
 * @extends AbstractHandler<\XF\Entity\ProfilePostComment>
 */
class ProfilePostCommentHandler extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['ProfilePost', 'ProfilePost.ProfileUser', 'ProfilePost.ProfileUser.Privacy'];
	}
}
