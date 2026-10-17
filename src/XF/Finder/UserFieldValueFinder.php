<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserFieldValue>
 */
class UserFieldValueFinder extends Finder
{
}
