<?php

namespace XF\Option;

use XF\Entity\Option;

class Twitter extends AbstractOption
{
	public static function verifyTweetOption(array &$values, Option $option)
	{
		if (!empty($values['enabled']))
		{
			/** @var \XF\Validator\Twitter $twitterValidator */
			$twitterValidator = \XF::app()->validator(\XF\Validator\Twitter::class);

			if (!empty($values['via']))
			{
				$values['via'] = $twitterValidator->coerceValue($values['via']);
				if (!$twitterValidator->isValid($values['via']))
				{
					$option->error(\XF::phrase('please_enter_valid_twitter_name_using_alphanumeric'), $option->option_id);
					return false;
				}
			}

			if (!empty($values['related']))
			{
				$values['related'] = $twitterValidator->coerceValue($values['related']);
				if (!$twitterValidator->isValid($values['related']))
				{
					$option->error(\XF::phrase('please_enter_valid_twitter_name_using_alphanumeric'), $option->option_id);
					return false;
				}
			}
		}

		return true;
	}
}
