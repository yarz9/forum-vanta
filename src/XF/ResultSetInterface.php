<?php

namespace XF;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

interface ResultSetInterface
{
	/**
	 * @param string $type
	 * @param list<int> $ids
	 * @param bool $filterViewable
	 * @param array<string, array{string, int}> $results
	 *
	 * @return AbstractCollection|array<int, Entity>
	 */
	public function getResultSetData($type, array $ids, $filterViewable = true, ?array $results = null);
}
