<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Admin> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Admin> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Admin|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Admin>
 */
class AdminFinder extends Finder
{
}
