<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UnfurlResult> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UnfurlResult> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UnfurlResult|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UnfurlResult>
 */
class UnfurlResultFinder extends Finder
{
}
