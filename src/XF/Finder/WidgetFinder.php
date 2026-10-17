<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Widget> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Widget> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Widget|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Widget>
 */
class WidgetFinder extends Finder
{
}
