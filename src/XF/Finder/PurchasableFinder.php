<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Purchasable> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Purchasable> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Purchasable|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Purchasable>
 */
class PurchasableFinder extends Finder
{
}
