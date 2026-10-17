<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\DeletionLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\DeletionLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\DeletionLog|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\DeletionLog>
 */
class DeletionLogFinder extends Finder
{
}
