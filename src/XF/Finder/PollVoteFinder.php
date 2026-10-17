<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PollVote> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PollVote> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PollVote|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PollVote>
 */
class PollVoteFinder extends Finder
{
}
