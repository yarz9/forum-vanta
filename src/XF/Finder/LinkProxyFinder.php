<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\LinkProxy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\LinkProxy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\LinkProxy|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\LinkProxy>
 */
class LinkProxyFinder extends Finder
{
}
