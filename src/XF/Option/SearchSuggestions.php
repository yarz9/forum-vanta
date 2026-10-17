<?php

namespace XF\Option;

use XF\Entity\Option;

class SearchSuggestions extends AbstractOption
{
	/**
	 * @param array<string, mixed> $htmlParams
	 */
	public static function renderOption(
		Option $option,
		array $htmlParams
	): string
	{
		$autoCompleteSupported = \XF::app()->search()->isAutoCompleteSupported();

		return static::getTemplate(
			'admin:option_template_searchSuggestions',
			$option,
			$htmlParams,
			[
				'autoCompleteSupported' => $autoCompleteSupported,
			]
		);
	}

	/**
	 * @param array $value
	 */
	public static function verifyOption(&$value, Option $option): bool
	{
		$autoCompleteEnabled = $value['enabled'] ?? false;
		$autoCompleteSupported = \XF::app()->search()->isAutoCompleteSupported();
		if ($autoCompleteEnabled && !$autoCompleteSupported)
		{
			$option->error(
				\XF::phrase('configured_search_source_does_not_support_search_suggestions'),
				$option->option_id
			);
			return false;
		}

		return true;
	}
}
