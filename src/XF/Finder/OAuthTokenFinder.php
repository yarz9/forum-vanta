<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\OAuthToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\OAuthToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\OAuthToken|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\OAuthToken>
 */
class OAuthTokenFinder extends Finder
{
}
