<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\BookmarkItem> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\BookmarkItem> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\BookmarkItem|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\BookmarkItem>
 */
class BookmarkItemFinder extends Finder
{
}
