<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\TagResultCache> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\TagResultCache> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\TagResultCache|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\TagResultCache>
 */
class TagResultCacheFinder extends Finder
{
}
