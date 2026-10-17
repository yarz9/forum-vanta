<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Navigation> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Navigation> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Navigation|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Navigation>
 */
class NavigationFinder extends Finder
{
}
