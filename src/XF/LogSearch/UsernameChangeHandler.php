<?php

namespace XF\LogSearch;

use XF\Entity\User;
use XF\Entity\UsernameChange;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class UsernameChangeHandler extends AbstractHandler
{
	protected $searchFields = [
		'old_username',
		'new_username',
		'change_reason',
		'reject_reason',
	];

	protected function getFinderName()
	{
		return 'XF:UsernameChange';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with(['User', 'ChangeUser', 'Moderator']);
	}

	protected function getDateField()
	{
		return 'change_date';
	}

	protected function getRouteName()
	{
		return 'logs/username-change';
	}

	/**
	 * @param UsernameChange $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->old_username,
			$record->new_username,
			\XF::phrase($record->change_state),
		];
	}

	/**
	 * @param UsernameChange $record
	 *
	 * @return string|null
	 */
	protected function getHint(Entity $record)
	{
		if ($record->reject_reason)
		{
			return $record->reject_reason;
		}

		if ($record->change_reason)
		{
			return $record->change_reason;
		}

		return null;
	}

	/**
	 * @param UsernameChange $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->Moderator ?? $record->ChangeUser ?? null;
	}
}
