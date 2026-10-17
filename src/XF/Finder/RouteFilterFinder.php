<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\RouteFilter> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\RouteFilter> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\RouteFilter|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\RouteFilter>
 */
class RouteFilterFinder extends Finder
{
	public function orderLength($field, $direction = 'DESC')
	{
		$expression = $this->expression('LENGTH(%s)', $field);
		$this->order($expression, $direction);

		return $this;
	}
}
