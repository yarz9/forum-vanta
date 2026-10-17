<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\SitemapLogRepository;

use function in_array;

class SitemapExclude extends AbstractOption
{
	public static function renderCheckbox(Option $option, array $htmlParams)
	{
		/** @var SitemapLogRepository $sitemapRepo */
		$sitemapRepo = \XF::repository(SitemapLogRepository::class);
		$sitemapHandlers = $sitemapRepo->getSitemapHandlers();

		$value = [];
		$choices = [];
		foreach ($sitemapHandlers AS $type => $sitemapHandler)
		{
			if (empty($option->option_value[$type]))
			{
				$value[] = $type;
			}
			$choices[$type] = \XF::app()->getContentTypePhrase($type);
		}

		return static::getCheckboxRow($option, $htmlParams, $choices, $value);
	}

	public static function verifyOption(array &$choices, Option $option)
	{
		if ($option->isInsert())
		{
			// insert - just trust the default value
			return true;
		}

		$exclusions = [];

		/** @var SitemapLogRepository $sitemapRepo */
		$sitemapRepo = \XF::repository(SitemapLogRepository::class);
		$sitemapHandlers = $sitemapRepo->getSitemapHandlers();

		foreach ($sitemapHandlers AS $type => $sitemapHandler)
		{
			if (!in_array($type, $choices))
			{
				$exclusions[$type] = true;
			}
		}

		$choices = $exclusions;

		return true;
	}
}
