<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\OAuthClient> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\OAuthClient> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\OAuthClient|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\OAuthClient>
 */
class OAuthClientFinder extends Finder
{
}
