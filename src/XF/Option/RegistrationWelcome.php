<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Entity\User;
use XF\Repository\UserRepository;
use XF\Util\Arr;

use function strlen;

class RegistrationWelcome extends AbstractOption
{
	/**
	 * @param array $values
	 * @param Option $option
	 *
	 * @return bool
	 */
	public static function verifyOption(array &$values, Option $option)
	{
		if ($option->isInsert())
		{
			// insert - just trust the default value
			return true;
		}

		if (!empty($values['messageEnabled']))
		{
			$participants = Arr::stringToArray($values['messageParticipants'], '#\s*,\s*#');
			if (!$participants)
			{
				$option->error(\XF::phrase('please_enter_at_least_one_valid_recipient'), $option->option_id);
				return false;
			}

			// separate this out as it's very important it comes first
			$starterName = array_shift($participants);
			$starter = \XF::em()->findOne(User::class, ['username' => $starterName]);
			if (!$starter)
			{
				$option->error(\XF::phrase('the_following_recipients_could_not_be_found_x', ['names' => $starterName]), $option->option_id);
				return false;
			}

			/** @var UserRepository $userRepo */
			$userRepo = \XF::repository(UserRepository::class);
			$users = $userRepo->getUsersByNames($participants, $notFound, [], false);
			if ($notFound)
			{
				$option->error(\XF::phrase('the_following_recipients_could_not_be_found_x', ['names' => implode(', ', $notFound)]), $option->option_id);
				return false;
			}

			$values['messageParticipants'] = $users->keys();
			array_unshift($values['messageParticipants'], $starter->user_id);

			if (!$values['messageTitle'] && !$values['messageBody'])
			{
				$option->error(\XF::phrase('please_enter_valid_welcome_direct_message_contents'), $option->option_id);
				return false;
			}
		}
		else
		{
			unset($values['messageParticipants']);
		}

		if (!empty($values['emailEnabled']) && !strlen(trim($values['emailBody'])))
		{
			$option->error(\XF::phraseDeferred('you_must_enter_email_message_to_enable_welcome_email'), $option->option_id);
			return false;
		}

		return true;
	}
}
