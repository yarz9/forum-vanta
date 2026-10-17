<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\SpamTriggerLog;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class SpamTriggerLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'details',
		'result',
		'ip_address' => AbstractFieldSearch::NO_SPACES,
		'request_state',
	];

	protected function getFinderName()
	{
		return 'XF:SpamTriggerLog';
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
		return 'logs/spam-trigger';
	}

	/**
	 * @param SpamTriggerLog $record
	 *
	 * @return array|string|void
	 */
	protected function getLabel(Entity $record)
	{
		return [
			\XF::app()->getContentTypePhrase($record->content_type),
			$record->User->username ?? \XF::phrase('deleted_member'),
			\XF::phrase($record->result == 'moderated' ? 'moderated' : 'rejected'),
			$record->details,
		];
	}
}
