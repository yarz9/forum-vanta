<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\EditHistory> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\EditHistory> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\EditHistory|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\EditHistory>
 */
class EditHistoryFinder extends Finder
{
}
