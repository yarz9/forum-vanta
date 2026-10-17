<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Draft> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Draft> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Draft|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Draft>
 */
class DraftFinder extends Finder
{
}
