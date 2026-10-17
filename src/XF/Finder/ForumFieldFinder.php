<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ForumField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ForumField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ForumField|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ForumField>
 */
class ForumFieldFinder extends Finder
{
}
