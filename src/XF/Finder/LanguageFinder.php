<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Language> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Language> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Language|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Language>
 */
class LanguageFinder extends Finder
{
}
