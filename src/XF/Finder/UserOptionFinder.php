<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserOption> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserOption> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserOption|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserOption>
 */
class UserOptionFinder extends Finder
{
}
