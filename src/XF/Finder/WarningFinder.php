<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Warning> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Warning> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Warning|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Warning>
 */
class WarningFinder extends Finder
{
}
