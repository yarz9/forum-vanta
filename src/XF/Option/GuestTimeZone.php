<?php

namespace XF\Option;

use XF\Data\TimeZone;
use XF\Entity\Option;

class GuestTimeZone extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		/** @var TimeZone $tzData */
		$tzData = \XF::app()->data(TimeZone::class);

		return static::getSelectRow($option, $htmlParams, $tzData->getTimeZoneOptions());
	}
}
