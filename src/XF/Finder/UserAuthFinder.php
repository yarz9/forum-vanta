<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserAuth> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserAuth> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserAuth|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserAuth>
 */
class UserAuthFinder extends Finder
{
}
