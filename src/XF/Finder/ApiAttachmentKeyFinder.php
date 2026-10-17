<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ApiAttachmentKey> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ApiAttachmentKey> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ApiAttachmentKey|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ApiAttachmentKey>
 */
class ApiAttachmentKeyFinder extends Finder
{
}
