<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Oembed> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Oembed> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Oembed|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Oembed>
 */
class OembedFinder extends Finder
{
}
