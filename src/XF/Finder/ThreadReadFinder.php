<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ThreadRead> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ThreadRead> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ThreadRead|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ThreadRead>
 */
class ThreadReadFinder extends Finder
{
}
