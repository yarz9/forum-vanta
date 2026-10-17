<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Webhook> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Webhook> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Webhook|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Webhook>
 */
class WebhookFinder extends Finder
{
}
