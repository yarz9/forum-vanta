<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Attachment> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Attachment> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Attachment|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Attachment>
 */
class AttachmentFinder extends Finder
{
}
