<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Tag> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Tag> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Tag|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Tag>
 */
class TagFinder extends Finder
{
}
