<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PurchaseRequest> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PurchaseRequest> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PurchaseRequest|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PurchaseRequest>
 */
class PurchaseRequestFinder extends Finder
{
}
