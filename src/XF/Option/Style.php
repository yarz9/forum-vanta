<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\StyleRepository;

class Style extends AbstractOption
{
	public static function renderRadio(Option $option, array $htmlParams)
	{
		/** @var StyleRepository $styleRepo */
		$styleRepo = \XF::repository(StyleRepository::class);

		$forEmailStyle = ($option->option_id == 'defaultEmailStyleId');

		$choices = [];
		if ($forEmailStyle)
		{
			$choices[0] = \XF::phrase('use_default_style');
		}
		foreach ($styleRepo->getStyleTree(false)->getFlattened() AS $entry)
		{
			if ($entry['record']->user_selectable || $forEmailStyle)
			{
				$choices[$entry['record']->style_id] = $entry['record']->title;
			}
		}

		return static::getRadioRow($option, $htmlParams, $choices);
	}

	protected static $triggered = false;

	/**
	 * This can be used as a verification callback to force a style update (CSS rebuild).
	 *
	 * @param mixed $value
	 * @param Option $option
	 * @return bool
	 */
	public static function triggerStyleUpdate(&$value, Option $option)
	{
		if ($option->isInsert())
		{
			return true;
		}

		if (!static::$triggered)
		{
			\XF::repository(StyleRepository::class)->updateAllStylesLastModifiedDateLater();
			static::$triggered = true;
		}

		return true;
	}
}
