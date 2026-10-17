<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\ErrorLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class ErrorLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'message',
		'ip_address' => '/^[a-f0-9:\.]+$/',
		'exception_type' => AbstractFieldSearch::NO_SPACES,
		'filename' => AbstractFieldSearch::NO_SPACES,
		'trace_string',
		'request_state',
	];

	protected function getFinderName()
	{
		return 'XF:ErrorLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('User');
	}

	protected function getDateField()
	{
		return 'exception_date';
	}

	protected function getRouteName()
	{
		return 'logs/server-errors';
	}

	/**
	 * @param ErrorLog $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->User;
	}

	/**
	 * @param ErrorLog $record
	 *
	 * @return string|null
	 */
	protected function getLabel(Entity $record)
	{
		return $record->message;
	}
}
