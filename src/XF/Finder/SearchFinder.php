<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Search> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Search> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Search|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Search>
 */
class SearchFinder extends Finder
{
}
