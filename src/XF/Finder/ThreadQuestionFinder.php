<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ThreadQuestion> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ThreadQuestion> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ThreadQuestion|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ThreadQuestion>
 */
class ThreadQuestionFinder extends Finder
{
}
