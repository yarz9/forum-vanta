<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ErrorLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ErrorLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ErrorLog|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ErrorLog>
 */
class ErrorLogFinder extends Finder
{
}
