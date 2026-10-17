<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\AdminLog;
use XF\Entity\ChangeLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Repository\ChangeLogRepository;

class ChangeLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'field' => AbstractFieldSearch::NO_SPACES,
		'old_value',
		'new_value',
		'EditUser.username',
	];

	protected function getFinderName()
	{
		return 'XF:ChangeLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('EditUser');
	}

	protected function getDateField()
	{
		return 'edit_date';
	}

	protected function getRouteName()
	{
		// TODO: return based upon content type?
		return 'logs/user-change';
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		if ($results = parent::search($text, $limit, $previousMatchIds))
		{
			/** @var ChangeLogRepository $changeRepo */
			$changeRepo = $this->app->repository(ChangeLogRepository::class);

			$changeRepo->addDataToLogs($results);
		}

		return $results;
	}

	/**
	 * @param ChangeLog $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		// TODO: get content type title??

		$output = [
			\XF::app()->getContentTypePhrase($record->content_type),
			$record->content_id,
		];

		foreach (['label', 'old', 'new'] AS $key)
		{
			if ($record->DisplayEntry->$key)
			{
				$output[] = $record->DisplayEntry->$key;
			}
		}

		return $output;
	}

	/**
	 * @param AdminLog $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return $record->EditUser ?? null;
	}
}
