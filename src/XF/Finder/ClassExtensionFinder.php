<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ClassExtension> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ClassExtension> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ClassExtension|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ClassExtension>
 */
class ClassExtensionFinder extends Finder
{
}
