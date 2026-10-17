<?php

namespace XF\Repository;

use XF\Entity\CodeEventListener;
use XF\Finder\CodeEventListenerFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class CodeEventListenerRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findListenersForList()
	{
		$listeners = $this->finder(CodeEventListenerFinder::class)
			->order(['addon_id', 'event_id', 'execute_order']);

		return $listeners;
	}

	public function getListenerCacheData()
	{
		/** @var AbstractCollection|CodeEventListener[] $listeners */
		$listeners = $this->finder(CodeEventListenerFinder::class)
			->whereAddOnActive(['disableProcessing' => true])
			->where('active', 1)
			->order(['event_id', 'execute_order', 'addon_id'])
			->fetch();

		$cache = [];

		foreach ($listeners AS $listener)
		{
			$hint = $listener->hint !== '' ? $listener->hint : '_';
			$cache[$listener->event_id][$hint][] = [
				$listener->callback_class,
				$listener->callback_method,
			];
		}

		return $cache;
	}

	public function rebuildListenerCache()
	{
		$cache = $this->getListenerCacheData();
		\XF::registry()->set('codeEventListeners', $cache);
		return $cache;
	}
}
