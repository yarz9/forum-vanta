<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Forum> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Forum> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Forum|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Forum>
 */
class ForumFinder extends Finder
{
}
