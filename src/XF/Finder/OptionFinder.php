<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Option> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Option> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Option|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Option>
 */
class OptionFinder extends Finder
{
}
