<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PermissionEntry> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PermissionEntry> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PermissionEntry|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PermissionEntry>
 */
class PermissionEntryFinder extends Finder
{
}
