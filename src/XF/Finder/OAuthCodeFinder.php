<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\OAuthCode> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\OAuthCode> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\OAuthCode|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\OAuthCode>
 */
class OAuthCodeFinder extends Finder
{
}
