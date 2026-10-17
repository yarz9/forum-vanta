<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ThreadFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ThreadFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ThreadFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ThreadFieldValue>
 */
class ThreadFieldValueFinder extends Finder
{
}
