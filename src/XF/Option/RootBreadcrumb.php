<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\NavigationRepository;

class RootBreadcrumb extends AbstractOption
{
	public static function renderOptions(Option $option, array $htmlParams)
	{
		/** @var NavigationRepository $navRepo */
		$navRepo = \XF::repository(NavigationRepository::class);

		$choices = [
			'' => \XF::phrase('none'),
		];
		foreach ($navRepo->getTopLevelEntries() AS $entry)
		{
			if ($entry->navigation_id != \XF::app()->get('defaultNavigationId') && $entry->enabled)
			{
				$choices[$entry->navigation_id] = $entry->title;
			}
		}

		return static::getRadioRow($option, $htmlParams, $choices);
	}
}
