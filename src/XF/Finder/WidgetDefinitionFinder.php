<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\WidgetDefinition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\WidgetDefinition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\WidgetDefinition|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\WidgetDefinition>
 */
class WidgetDefinitionFinder extends Finder
{
}
