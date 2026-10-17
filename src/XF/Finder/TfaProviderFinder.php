<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\TfaProvider> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\TfaProvider> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\TfaProvider|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\TfaProvider>
 */
class TfaProviderFinder extends Finder
{
}
