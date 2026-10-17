<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Permission> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Permission> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Permission|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Permission>
 */
class PermissionFinder extends Finder
{
}
