<?php

namespace XF\Reaction;

use XF\Entity\ReactionContent;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<\XF\Entity\ConversationMessage>
 */
class ConversationMessageHandler extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return false;
	}

	public function publishReactionNewsFeed(User $sender, $contentId, Entity $content, $reactionId)
	{
	}

	public function unpublishReactionNewsFeed(ReactionContent $reactionContent)
	{
	}
}
