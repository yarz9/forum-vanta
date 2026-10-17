<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\ModeratorLog;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class ModeratorLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'content_title',
		'content_username',
		'ip_address' => '/^[a-f0-9:\.]+$/',
		'content_url' => AbstractFieldSearch::NO_SPACES,
	];

	protected function getFinderName()
	{
		return 'XF:ModeratorLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with(['User', 'ContentUser']);
	}

	protected function getDateField()
	{
		return 'log_date';
	}

	protected function getRouteName()
	{
		return 'logs/moderator';
	}

	/**
	 * @param ModeratorLog $record
	 *
	 * @return array
	 */
	protected function getLabel(Entity $record)
	{
		return [
			\XF::app()->getContentTypePhrase($record->content_type),
			$record->content_title,
			$record->action,
		];
	}

	protected function getLogUser(Entity $record)
	{
		return $record->User;
	}
}
