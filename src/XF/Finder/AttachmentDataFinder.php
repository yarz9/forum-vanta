<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\AttachmentData> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\AttachmentData> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\AttachmentData|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\AttachmentData>
 */
class AttachmentDataFinder extends Finder
{
}
