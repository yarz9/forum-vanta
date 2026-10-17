<?php

namespace XF\Finder;

use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ConversationMessage> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ConversationMessage> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ConversationMessage|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ConversationMessage>
 */
class ConversationMessageFinder extends Finder
{
	public function inConversation(ConversationMaster $conversation)
	{
		$this->where('conversation_id', $conversation->conversation_id);

		return $this;
	}

	public function earlierThan(ConversationMessage $message)
	{
		$this->where('message_date', '<', $message->message_date);

		return $this;
	}
}
