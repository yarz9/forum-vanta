<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserRemember> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserRemember> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserRemember|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserRemember>
 */
class UserRememberFinder extends Finder
{
}
