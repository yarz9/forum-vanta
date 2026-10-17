<?php

namespace XF\Repository;

use XF\Entity\ForumPrefix;
use XF\Entity\ThreadPrefix;
use XF\Finder\ForumFinder;
use XF\Finder\ForumPrefixFinder;

class ThreadPrefixRepository extends AbstractPrefix
{
	protected function getRegistryKey()
	{
		return 'threadPrefixes';
	}

	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrefix';
	}

	public function getVisiblePrefixListData()
	{
		$forums = $this->finder(ForumFinder::class)
			->with('Node')
			->with('Node.Permissions|' . \XF::visitor()->permission_combination_id)
			->fetch();

		$prefixMap = $this->finder(ForumPrefixFinder::class)
			->fetch()
			->groupBy('prefix_id', 'node_id');

		$isVisibleClosure = function (ThreadPrefix $prefix) use ($prefixMap, $forums)
		{
			if (!isset($prefixMap[$prefix->prefix_id]))
			{
				return false;
			}

			$isVisible = false;

			foreach ($prefixMap[$prefix->prefix_id] AS $forumPrefix)
			{
				/** @var ForumPrefix $forumPrefix */
				if (!isset($forums[$forumPrefix->node_id]))
				{
					continue;
				}

				$isVisible = $forums[$forumPrefix->node_id]->canView();

				if ($isVisible)
				{
					break;
				}
			}

			return $isVisible;
		};
		return $this->_getVisiblePrefixListData($isVisibleClosure);
	}
}
