<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\PaymentProvider> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\PaymentProvider> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\PaymentProvider|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\PaymentProvider>
 */
class PaymentProviderFinder extends Finder
{
}
