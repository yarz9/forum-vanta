<?php

namespace XF\Repository;

use XF\Finder\AdvertisingFinder;
use XF\Finder\AdvertisingPositionFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Service\Advertising\WriterService;

use function count;

class AdvertisingRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findAdsForList()
	{
		return $this->finder(AdvertisingFinder::class)->order('display_order');
	}

	/**
	 * @return Finder
	 */
	public function findAdvertisingPositionsForList($activeOnly = false)
	{
		$finder = $this->finder(AdvertisingPositionFinder::class)->order('position_id');
		if ($activeOnly)
		{
			$finder->with('AddOn')
				->whereAddOnActive()
				->where('active', 1);
		}
		return $finder;
	}

	public function getTotalGroupedAds(array $groupedAds)
	{
		$total = 0;

		foreach ($groupedAds AS $ads)
		{
			$total += count($ads);
		}

		return $total;
	}

	public function writeAdsTemplate($disallowedTemplates = null)
	{
		$positions = $this->findAdvertisingPositionsForList(true)
			->fetch()
			->toArray();

		$ads = $this->finder(AdvertisingFinder::class)
			->where('position_id', array_keys($positions))
			->where('active', 1)
			->order('display_order')
			->fetch()
			->groupBy('position_id');

		/** @var WriterService $service */
		$service = $this->app()->service(WriterService::class, $positions, $ads);

		if ($disallowedTemplates === null && !empty($this->options()->adsDisallowedTemplates))
		{
			$disallowedTemplates = $this->options()->adsDisallowedTemplates;
		}
		if ($disallowedTemplates)
		{
			$service->setDisallowedTemplates($disallowedTemplates);
		}

		$service->write();
	}
}
