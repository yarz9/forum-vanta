<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ForumRead> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ForumRead> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ForumRead|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ForumRead>
 */
class ForumReadFinder extends Finder
{
}
