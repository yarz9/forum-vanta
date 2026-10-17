<?php

namespace XF\Repository;

use XF\Finder\CodeEventFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class CodeEventRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findEventsForList()
	{
		return $this->finder(CodeEventFinder::class)->order(['event_id']);
	}

	public function getEventTitlePairs()
	{
		return $this->findEventsForList()
			->fetch()
			->pluckNamed('event_id', 'event_id');
	}
}
