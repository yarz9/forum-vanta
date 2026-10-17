<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Reaction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Reaction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Reaction|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Reaction>
 */
class ReactionFinder extends Finder
{
}
