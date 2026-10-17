<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\SearchForumCache> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\SearchForumCache> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\SearchForumCache|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\SearchForumCache>
 */
class SearchForumCacheFinder extends Finder
{
}
