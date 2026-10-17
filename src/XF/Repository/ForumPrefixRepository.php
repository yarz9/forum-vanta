<?php

namespace XF\Repository;

use XF\Entity\AbstractPrefix;
use XF\Entity\Forum;

class ForumPrefixRepository extends AbstractPrefixMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XF:ForumPrefix';
	}

	protected function getAssociationsForPrefix(AbstractPrefix $prefix)
	{
		return $prefix->getRelation('ForumPrefixes');
	}

	protected function updateAssociationCache(array $cache)
	{
		$nodeIds = array_keys($cache);
		$forums = $this->em->findByIds(Forum::class, $nodeIds);

		foreach ($forums AS $forum)
		{
			/** @var Forum $forum */
			$forum->prefix_cache = $cache[$forum->node_id];
			$forum->saveIfChanged();
		}
	}
}
