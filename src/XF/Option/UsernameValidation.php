<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Util\Php;

class UsernameValidation extends AbstractOption
{
	public static function verifyOption(&$value, Option $option)
	{
		if ($value['matchRegex'] !== '')
		{
			if (!Php::isValidRegex($value['matchRegex']))
			{
				$option->error(\XF::phrase('invalid_regular_expression'), $option->option_id);
				return false;
			}
		}

		return true;
	}
}
