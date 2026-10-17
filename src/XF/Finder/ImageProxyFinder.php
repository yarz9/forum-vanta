<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ImageProxy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ImageProxy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ImageProxy|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ImageProxy>
 */
class ImageProxyFinder extends Finder
{
}
