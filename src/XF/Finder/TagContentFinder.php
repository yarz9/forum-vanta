<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\TagContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\TagContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\TagContent|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\TagContent>
 */
class TagContentFinder extends Finder
{
}
