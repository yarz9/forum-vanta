<?php

namespace XF\Webhook\Criteria;

use function in_array;

class Thread extends AbstractCriteria
{
	protected function _matchNodeId(array $criteria, array $data): bool
	{
		if (isset($criteria['search_type']) && $criteria['search_type'] === 'exclude')
		{
			$matchInForums = false;
		}
		else
		{
			$matchInForums = true;
		}
		unset($criteria['search_type']);

		if (isset($criteria['node_ids'][0]) && $criteria['node_ids'][0] == 0)
		{
			return $matchInForums;
		}

		if ($matchInForums && in_array($data['node_id'], $criteria['node_ids']))
		{
			return true;
		}

		if (!$matchInForums && !in_array($data['node_id'], $criteria['node_ids']))
		{
			return true;
		}

		return false;
	}
}
