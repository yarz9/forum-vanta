<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ThreadPrefix> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ThreadPrefix> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ThreadPrefix|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ThreadPrefix>
 */
class ThreadPrefixFinder extends Finder
{
}
