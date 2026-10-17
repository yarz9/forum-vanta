<?php

namespace XF\LogSearch;

use XF\Entity\SpamCleanerLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class SpamCleanerLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'username',
		'applying_username',
		'data',
	];

	protected function getFinderName()
	{
		return 'XF:SpamCleanerLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('ApplyingUser');
	}

	protected function getDateField()
	{
		return 'application_date';
	}

	protected function getRouteName()
	{
		// TODO: return based upon content type?
		return 'logs/user-change';
	}

	/**
	 * @param SpamCleanerLog $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		reset($record->data);

		return [
			$record->username,
			\XF::app()->getContentTypePhrase(key($record->data)),
		];
	}

	/**
	 * @param SpamCleanerLog $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->ApplyingUser;
	}
}
