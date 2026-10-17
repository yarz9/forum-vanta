<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PaymentProfile> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PaymentProfile> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PaymentProfile|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PaymentProfile>
 */
class PaymentProfileFinder extends Finder
{
}
