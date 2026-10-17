<?php

namespace XF\LogSearch;

use XF\Entity\AdminLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class AdminLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'ip_address' => '/^[a-f0-9:\.]+$/',
		'request_url' => self::NO_SPACES,
		'request_data',
		'User.username',
	];

	protected function getFinderName()
	{
		return 'XF:AdminLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('User');
	}

	protected function getDateField()
	{
		return 'request_date';
	}

	protected function getRouteName()
	{
		return 'logs/admin';
	}

	/**
	 * @param AdminLog $record
	 *
	 * @return string
	 */
	protected function getLabel(Entity $record)
	{
		return $record->request_url;
	}

	/**
	 * @param AdminLog $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->User ?? null;
	}

	public function isSearchable()
	{
		return \XF::visitor()->is_super_admin;
	}
}
