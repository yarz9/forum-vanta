<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ContentTypeField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ContentTypeField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ContentTypeField|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ContentTypeField>
 */
class ContentTypeFieldFinder extends Finder
{
}
