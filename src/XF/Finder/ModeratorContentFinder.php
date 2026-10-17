<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ModeratorContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ModeratorContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ModeratorContent|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ModeratorContent>
 */
class ModeratorContentFinder extends Finder
{
}
