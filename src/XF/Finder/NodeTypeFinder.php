<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\NodeType> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\NodeType> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\NodeType|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\NodeType>
 */
class NodeTypeFinder extends Finder
{
}
