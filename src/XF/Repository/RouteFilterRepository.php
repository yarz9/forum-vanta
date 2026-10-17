<?php

namespace XF\Repository;

use XF\Finder\RouteFilterFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class RouteFilterRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findRouteFiltersForList()
	{
		return $this->finder(RouteFilterFinder::class)->order(['route_filter_id']);
	}

	public function getRouteFilterCacheData()
	{
		/** @var RouteFilterFinder $finder */
		$finder = $this->finder(RouteFilterFinder::class);

		$results = $finder->where('enabled', 1)
			->orderLength('replace_route')
			->fetch();

		$in = [];
		foreach ($results AS $result)
		{
			$in[$result->route_filter_id] = [
				'find_route' => $result->find_route,
				'replace_route' => $result->replace_route,
			];
		}

		$results = $finder->where('url_to_route_only', 0)
			->resetOrder()
			->orderLength('find_route')
			->order('prefix')
			->fetch();

		$out = [];
		foreach ($results AS $result)
		{
			$out[$result->prefix][$result->route_filter_id] = [
				'find_route' => $result->find_route,
				'replace_route' => $result->replace_route,
			];
		}

		return [
			'in' => $in,
			'out' => $out,
		];
	}

	public function rebuildRouteFilterCache()
	{
		$caches = $this->getRouteFilterCacheData();
		\XF::registry()->set('routeFilters', $caches);

		return $caches;
	}
}
