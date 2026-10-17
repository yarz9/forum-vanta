<?php

namespace XF\LogSearch;

use XF\Entity\ImageProxy;
use XF\Mvc\Entity\Entity;

class ImageProxyHandler extends AbstractHandler
{
	protected $searchFields = [
		'url',
		'file_name',
	];

	protected function getFinderName()
	{
		return 'XF:ImageProxy';
	}

	protected function getDateField()
	{
		return 'last_request_date';
	}

	protected function getRouteName()
	{
		return 'logs/image-proxy';
	}

	/**
	 * @param ImageProxy $record
	 *
	 * @return array|string|void
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->file_name,
			$record->mime_type,
			$record->file_size,
			$record->url,
		];
	}
}
