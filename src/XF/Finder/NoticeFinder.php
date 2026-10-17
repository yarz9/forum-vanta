<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Notice> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Notice> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Notice|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Notice>
 */
class NoticeFinder extends Finder
{
}
