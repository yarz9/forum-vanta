<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\HelpPage> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\HelpPage> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\HelpPage|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\HelpPage>
 */
class HelpPageFinder extends Finder
{
}
