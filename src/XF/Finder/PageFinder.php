<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Page> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Page> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Page|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Page>
 */
class PageFinder extends Finder
{
}
