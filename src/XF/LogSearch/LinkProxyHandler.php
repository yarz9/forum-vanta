<?php

namespace XF\LogSearch;

use XF\Entity\LinkProxy;
use XF\Mvc\Entity\Entity;

class LinkProxyHandler extends AbstractHandler
{
	protected $searchFields = [
		'url',
	];

	protected function getFinderName()
	{
		return 'XF:LinkProxy';
	}

	protected function getDateField()
	{
		return 'last_request_date';
	}

	protected function getRouteName()
	{
		return 'logs/link-proxy';
	}

	/**
	 * @param LinkProxy $record
	 *
	 * @return array|string|void
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->url,
			$record->hits,
		];
	}

}
