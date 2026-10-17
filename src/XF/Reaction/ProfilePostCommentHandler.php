<?php

namespace XF\Reaction;

use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<\XF\Entity\ProfilePostComment>
 */
class ProfilePostCommentHandler extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->message_state == 'visible');
	}

	public function getEntityWith()
	{
		return ['ProfilePost', 'ProfilePost.ProfileUser', 'ProfilePost.ProfileUser.Privacy'];
	}
}
