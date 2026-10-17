<?php

namespace XF\Job;

use XF\Service\AbstractNotifier;

use function call_user_func;

class Notifier extends AbstractJob
{
	protected $defaultData = [
		'service' => '',
		'extra' => [],
		'notifyData' => [],
		'alerted' => [],
		'emailed' => [],
	];

	public function run($maxRunTime)
	{
		$service = $this->app->extendClass($this->data['service']);
		$call = [$service, 'createForJob'];

		try
		{
			$validService = class_exists($service) && is_callable($call);
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, 'Error creating notifier service: ');
			$validService = false;
		}

		if (!$validService)
		{
			return $this->complete();
		}

		/** @var AbstractNotifier|null $notifier */
		$notifier = call_user_func($call, $this->data['extra']);
		if (!$notifier)
		{
			return $this->complete();
		}

		$notifier->setupFromJobData($this->data);
		$notifier->notify($maxRunTime);
		if (!$notifier->hasMore())
		{
			return $this->complete();
		}

		$this->data = $notifier->getJobData();
		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('running');
		$typePhrase = 'Notifications'; // never seen
		return sprintf('%s... %s', $actionPhrase, $typePhrase);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
