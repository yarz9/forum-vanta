<?php

namespace XF\LogSearch;

use XF\Entity\Oembed;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class OembedHandler extends AbstractHandler
{
	protected $searchFields = [
		'title',
		'media_id',
		'media_site_id',
	];

	protected function getFinderName()
	{
		return 'XF:Oembed';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('BbCodeMediaSite');
	}

	protected function getDateField()
	{
		return 'last_request_date';
	}

	protected function getRouteName()
	{
		return 'logs/oembed';
	}

	/**
	 * @param Oembed $record
	 *
	 * @return array|string|void
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->BbCodeMediaSite->site_title,
			$record->title,
		];
	}
}
