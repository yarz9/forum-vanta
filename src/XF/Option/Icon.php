<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\IconRepository;

class Icon extends AbstractOption
{
	/**
	 * @param mixed $value
	 */
	public static function verifyOption(&$value, Option $option): bool
	{
		if ($option->isInsert())
		{
			return true;
		}

		$iconRepo = \XF::repository(IconRepository::class);

		$classLines = preg_split('/\r?\n/', $value, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($classLines AS $classes)
		{
			$icons = $iconRepo->getIconsFromClasses($classes);
			if (!$icons)
			{
				$option->error(\XF::phrase('please_enter_valid_icon_class'));
				return false;
			}
		}

		$iconRepo->enqueueUsageAnalyzer('extra');
		return true;
	}
}
