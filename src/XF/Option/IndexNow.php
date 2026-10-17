<?php

namespace XF\Option;

use XF\Entity\Option;

class IndexNow extends AbstractOption
{
	protected static function canUseIndexNow(&$error = null): bool
	{
		$options = \XF::options();
		if (!$options->useFriendlyUrls)
		{
			$error = \XF::phrase('friendly_urls_must_be_enabled_to_use_indexnow');
			return false;
		}

		return true;
	}

	public static function renderOption(Option $option, array $htmlParams): string
	{
		$canUseIndexNow = static::canUseIndexNow($error);

		return static::getTemplate('admin:option_template_indexNow', $option, $htmlParams, [
			'canUseIndexNow' => $canUseIndexNow,
			'error' => $error,
		]);
	}

	public static function verifyOption(&$value, Option $option): bool
	{
		if (empty($value['enabled']))
		{
			if ($option->option_value['enabled'])
			{
				$value['key'] = false;
			}

			return true;
		}

		$options = \XF::options();
		if (!$option->option_value['enabled'] && $options->useFriendlyUrls)
		{
			$value['key'] = str_replace('_', '', \XF::generateRandomString(32));
		}

		return true;
	}
}
