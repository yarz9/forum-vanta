<?php

namespace XF\Repository;

use XF\Finder\NodeTypeFinder;
use XF\Mvc\Entity\Repository;

class NodeTypeRepository extends Repository
{
	public function getNodeTypeCacheData()
	{
		$output = [];

		foreach ($this->finder(NodeTypeFinder::class)->fetch() AS $nodeType)
		{
			$output[$nodeType->node_type_id] = $nodeType->toArray(false);
		}

		return $output;
	}

	public function rebuildNodeTypeCache()
	{
		$cache = $this->getNodeTypeCacheData();
		\XF::registry()->set('nodeTypes', $cache);
		return $cache;
	}
}
