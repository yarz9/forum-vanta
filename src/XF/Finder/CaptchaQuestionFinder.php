<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\CaptchaQuestion> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\CaptchaQuestion> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\CaptchaQuestion|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\CaptchaQuestion>
 */
class CaptchaQuestionFinder extends Finder
{
}
