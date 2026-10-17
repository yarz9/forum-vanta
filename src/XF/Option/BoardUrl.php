<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Validator\Url;

use function in_array;

class BoardUrl extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		$htmlParams['inputType'] = 'url';
		$htmlParams['explainHtml'] = \XF::phrase('option_explain.' . $option->option_id, [
			'suggestedUrl' => static::getSuggestedUrl(),
		]);

		return static::getTextboxRow($option, $htmlParams);
	}

	public static function verifyOption(&$value, Option $option)
	{
		if ($option->isInsert())
		{
			// always allow a new value to be submitted so we don't blow up installation
			return true;
		}

		$urlValidator = \XF::app()->validator(Url::class);
		$value = $urlValidator->coerceValue($value);
		if (!$urlValidator->isValid($value))
		{
			$option->error(\XF::phrase('please_enter_valid_url'), $option->option_id);
			return false;
		}

		if (!static::isValidBoardUrl($value))
		{
			$option->error(\XF::phrase('option_explain.boardUrl', [
				'suggestedUrl' => static::getSuggestedUrl(),
			]), $option->option_id);
		}

		return true;
	}

	public static function isValidBoardUrl($value)
	{
		if (in_array(substr($value, -1), ['/', '?', '#']))
		{
			return false;
		}

		$parts = parse_url($value);

		if (isset($parts['query']))
		{
			return false;
		}

		if (isset($parts['fragment']))
		{
			return false;
		}

		if (isset($parts['path']) && preg_match('#/[^/]+\.(php|html)$#i', $parts['path']))
		{
			return false;
		}

		return true;
	}

	public static function getSuggestedUrl()
	{
		return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . preg_replace('#/admin.php$#', '', $_SERVER['SCRIPT_NAME']);
	}
}
