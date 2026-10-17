<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Ip> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Ip> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Ip|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Ip>
 */
class IpFinder extends Finder
{
}
