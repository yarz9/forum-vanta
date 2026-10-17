<?php

namespace XF\Option;

use XF\Entity\Option;

class EmailDkim extends AbstractOption
{
	protected static function canUseEmailDkim(&$error = null): bool
	{
		if (!extension_loaded('openssl'))
		{
			$error = \XF::phrase('required_php_extension_x_not_found', [
				'extension' => 'openssl',
			]);
			return false;
		}

		return true;
	}

	public static function renderOption(Option $option, array $htmlParams): string
	{
		$canUseEmailDkim = static::canUseEmailDkim($error);

		return static::getTemplate('admin:option_template_emailDkim', $option, $htmlParams, [
			'canUseEmailDkim' => $canUseEmailDkim,
			'error' => $error,
		]);
	}
}
