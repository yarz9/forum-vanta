<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ConversationRecipient> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ConversationRecipient> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ConversationRecipient|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ConversationRecipient>
 */
class ConversationRecipientFinder extends Finder
{
}
