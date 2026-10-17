<?php

namespace XF\LogSearch;

use XF\Entity\User;
use XF\Entity\UserReject;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class UserRejectHandler extends AbstractHandler
{
	protected $searchFields = [
		'User.username',
		'reject_reason',
	];

	protected function getFinderName()
	{
		return 'XF:UserReject';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with(['User', 'RejectUser']);
	}

	protected function getDateField()
	{
		return 'reject_date';
	}

	protected function getRouteName()
	{
		return 'logs/rejected-users';
	}

	/**
	 * @param UserReject $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->User->username ?? \XF::phrase('deleted_member'),
			$record->reject_reason,
		];
	}

	/**
	 * @param UserRejectHandler $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->RejectUser;
	}
}
