<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ApprovalQueue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ApprovalQueue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ApprovalQueue|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ApprovalQueue>
 */
class ApprovalQueueFinder extends Finder
{
}
