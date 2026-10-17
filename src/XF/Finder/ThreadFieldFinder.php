<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ThreadField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ThreadField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ThreadField|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ThreadField>
 */
class ThreadFieldFinder extends Finder
{
}
