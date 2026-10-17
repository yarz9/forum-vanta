<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\SearchForum> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\SearchForum> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\SearchForum|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\SearchForum>
 */
class SearchForumFinder extends Finder
{
}
