<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Finder\PhraseFinder;
use XF\Util\Php;

class Phrase extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		return static::getTemplate('admin:option_template_phrase', $option, $htmlParams, [
			'phrase' => static::getMasterPhrase($option->option_id),
		]);
	}

	public static function verifyOption(&$value, Option $option, $optionId)
	{
		$phrase = static::getMasterPhrase($optionId);
		$phrase->phrase_text = $value;
		$phrase->save();

		$value = $phrase->title;
	}

	/**
	 * @param string $optionId
	 * @param integer|null $index Allows overrides of the base phrase for other purposes
	 *
	 * @return string
	 */
	public static function getPhraseName($optionId, $index = 'default')
	{
		return Php::fromCamelCase($optionId) . '.' . $index;
	}

	/**
	 * @param string $optionId
	 * @param integer|null $index Allows overrides of the base phrase for other purposes
	 *
	 * @return PhraseFinder
	 */
	protected static function getPhraseFinder($optionId, $index = 'default')
	{
		return \XF::app()->finder(PhraseFinder::class)
			->where('title', static::getPhraseName($optionId, $index))
			->where('language_id', 0)
			->where('addon_id', '');
	}

	public static function getMasterPhrase($optionId, $index = 'default')
	{
		$phrase = static::getPhraseFinder($optionId, $index)->fetchOne();
		if (!$phrase)
		{
			$phrase = \XF::app()->em()->create(\XF\Entity\Phrase::class);
			$phrase->title = static::getPhraseName($optionId, $index);
			$phrase->addon_id = '';
			$phrase->language_id = 0;
		}

		return $phrase;
	}
}
