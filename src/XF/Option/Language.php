<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\LanguageRepository;

class Language extends AbstractOption
{
	public static function renderRadio(Option $option, array $htmlParams)
	{
		/** @var LanguageRepository $languageRepo */
		$languageRepo = \XF::repository(LanguageRepository::class);

		$choices = [];
		foreach ($languageRepo->getLanguageTree(false)->getFlattened() AS $entry)
		{
			$choices[$entry['record']->language_id] = $entry['record']->title;
		}

		return static::getRadioRow($option, $htmlParams, $choices);
	}
}
