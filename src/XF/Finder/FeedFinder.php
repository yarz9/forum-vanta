<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Feed> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Feed> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Feed|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Feed>
 */
class FeedFinder extends Finder
{
	public function isDue($time = null)
	{
		$expression = $this->expression('last_fetch + frequency');
		$this->where($expression, '<', $time ?: time());

		return $this;
	}
}
