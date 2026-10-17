<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\SpamTriggerLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\SpamTriggerLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\SpamTriggerLog|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\SpamTriggerLog>
 */
class SpamTriggerLogFinder extends Finder
{
	public function forContent($contentType, $contentId)
	{
		$this->where('content_type', $contentType)
			->where('content_id', $contentId);

		return $this;
	}
}
