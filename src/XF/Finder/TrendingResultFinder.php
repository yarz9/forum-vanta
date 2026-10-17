<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\TrendingResult> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\TrendingResult> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\TrendingResult|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\TrendingResult>
 */
class TrendingResultFinder extends Finder
{
}
