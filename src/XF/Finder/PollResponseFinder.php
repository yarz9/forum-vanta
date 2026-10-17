<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PollResponse> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PollResponse> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PollResponse|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PollResponse>
 */
class PollResponseFinder extends Finder
{
}
