<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ApiKey> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ApiKey> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ApiKey|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ApiKey>
 */
class ApiKeyFinder extends Finder
{
}
