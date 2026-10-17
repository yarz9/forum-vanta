<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\CronEntry> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\CronEntry> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\CronEntry|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\CronEntry>
 */
class CronEntryFinder extends Finder
{
}
