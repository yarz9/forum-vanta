<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\OAuthRequest> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\OAuthRequest> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\OAuthRequest|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\OAuthRequest>
 */
class OAuthRequestFinder extends Finder
{
}
