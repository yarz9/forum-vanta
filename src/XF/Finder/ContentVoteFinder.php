<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ContentVote> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ContentVote> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ContentVote|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ContentVote>
 */
class ContentVoteFinder extends Finder
{
}
