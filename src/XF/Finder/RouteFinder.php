<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Route> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Route> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Route|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Route>
 */
class RouteFinder extends Finder
{
}
