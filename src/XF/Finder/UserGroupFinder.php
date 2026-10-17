<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserGroup|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserGroup>
 */
class UserGroupFinder extends Finder
{
}
