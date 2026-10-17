<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserProfile> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserProfile> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserProfile|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserProfile>
 */
class UserProfileFinder extends Finder
{
}
