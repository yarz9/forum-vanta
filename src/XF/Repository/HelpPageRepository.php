<?php

namespace XF\Repository;

use XF\Finder\HelpPageFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class HelpPageRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findHelpPagesForList()
	{
		return $this->finder(HelpPageFinder::class)
			->setDefaultOrder('display_order');
	}

	/**
	 * @return Finder
	 */
	public function findActiveHelpPages()
	{
		return $this->findHelpPagesForList()
			->where('active', 1)
			->whereAddOnActive();
	}

	public function getHelpPageCount()
	{
		return $this->findActiveHelpPages()
			->total();
	}

	public function rebuildHelpPageCount()
	{
		$cache = $this->getHelpPageCount();
		\XF::registry()->set('helpPageCount', $cache);
		return $cache;
	}
}
