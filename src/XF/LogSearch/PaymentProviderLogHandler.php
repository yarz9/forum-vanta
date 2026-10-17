<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\PaymentProviderLog;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;

class PaymentProviderLogHandler extends AbstractHandler
{
	protected $searchFields = [
		'purchase_request_key' => AbstractFieldSearch::NO_SPACES,
		'transaction_id' => AbstractFieldSearch::NO_SPACES,
		'log_message',
		'log_details',
	];

	protected function getFinderName()
	{
		return 'XF:PaymentProviderLog';
	}

	protected function getFinderConditions(Finder &$finder)
	{
		$finder->with('Provider');
	}

	protected function getDateField()
	{
		return 'log_date';
	}

	protected function getRouteName()
	{
		return 'logs/payment-provider';
	}

	/**
	 * @param PaymentProviderLog $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		return [
			$record->Provider->title,
			$record->log_message,
		];
	}
}
