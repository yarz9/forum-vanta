<?php

namespace XF\Finder;

use XF\Entity\Node;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Node> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Node> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Node|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Node>
 */
class NodeFinder extends Finder
{
	public function descendantOf(Node $node)
	{
		$this->where('lft', '>', $node->lft)
			->where('rgt', '<', $node->rgt);

		return $this;
	}

	public function listable()
	{
		$this->where('display_in_list', 1);

		return $this;
	}
}
