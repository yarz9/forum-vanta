<?php

namespace XF\Repository;

use XF\Finder\ClassExtensionFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ClassExtensionRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findExtensionsForList()
	{
		$listeners = $this->finder(ClassExtensionFinder::class)
			->order(['from_class', 'to_class', 'execute_order']);

		return $listeners;
	}

	public function getExtensionCacheData()
	{
		$extensions = $this->finder(ClassExtensionFinder::class)
			->whereAddOnActive(['disableProcessing' => true])
			->where('active', 1)
			->order(['execute_order', 'to_class'])
			->fetch();

		$cache = [];

		foreach ($extensions AS $extension)
		{
			$fromClass = \XF::getClassForAlias($extension->from_class);
			$cache[$fromClass][] = $extension->to_class;
		}

		return $cache;
	}

	public function rebuildExtensionCache()
	{
		$cache = $this->getExtensionCacheData();
		\XF::registry()->set('classExtensions', $cache);
		return $cache;
	}
}
