<?php

namespace XF\Job;

use XF\Entity\Feed;
use XF\Mvc\Entity\ArrayCollection;
use XF\Repository\FeedRepository;
use XF\Service\Feed\FeederService;

class Feeder extends AbstractJob
{
	protected $defaultData = [
		'steps' => 0,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);

		$this->data['steps']++;

		/** @var FeedRepository $feedRepo */
		$feedRepo = $this->app->repository(FeedRepository::class);

		/** @var FeederService $feederService */
		$feederService = $this->app->service(FeederService::class);

		/** @var Feed[]|ArrayCollection $dueFeeds */
		$dueFeeds = $feedRepo->findDueFeeds()->fetch();
		if (!$dueFeeds->count())
		{
			return $this->complete();
		}

		foreach ($dueFeeds AS $feed)
		{
			if (!$feed->Forum)
			{
				continue;
			}

			if ($feederService->setupImport($feed) && $feederService->countPendingEntries())
			{
				$feederService->importEntries();
			}

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('fetching');
		$typePhrase = \XF::phrase('registered_feeds');
		return sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat('. ', $this->data['steps']));
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
