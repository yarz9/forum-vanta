<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Poll> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Poll> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Poll|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Poll>
 */
class PollFinder extends Finder
{
}
