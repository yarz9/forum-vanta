<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\FeaturedContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\FeaturedContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\FeaturedContent|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\FeaturedContent>
 */
class FeaturedContentFinder extends Finder
{
}
