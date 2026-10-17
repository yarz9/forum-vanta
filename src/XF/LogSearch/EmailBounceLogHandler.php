<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\EmailBounceLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class EmailBounceLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'recipient' => AbstractFieldSearch::NO_SPACES,
		'diagnostic_info',
	];

	protected function getFinderName()
	{
		return 'XF:EmailBounceLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('User');
	}

	protected function getDateField()
	{
		return 'log_date';
	}

	protected function getRouteName()
	{
		return 'logs/email-bounces';
	}

	/**
	 * @param EmailBounceLog $record
	 *
	 * @return array|string|void
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->recipient,
			$record->diagnostic_info,
		];
	}

	/**
	 * @param EmailBounceLog $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->User;
	}

}
