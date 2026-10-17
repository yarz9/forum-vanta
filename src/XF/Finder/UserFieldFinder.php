<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserField|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserField>
 */
class UserFieldFinder extends Finder
{
}
