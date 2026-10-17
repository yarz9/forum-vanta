<?php

namespace XF\Service\Feed;

use XF\Entity\Feed;
use XF\Entity\FeedLog;
use XF\Entity\Thread;
use XF\Finder\FeedLogFinder;
use XF\PrintableException;
use XF\Repository\ThreadWatchRepository;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;
use XF\Service\Thread\CreatorService;
use XF\Util\Str;

use function count;

class FeederService extends AbstractService
{
	/** @var Feed */
	protected $feed;

	protected $feedData;

	protected $pendingEntries;

	public function setupImport(Feed $feed, $haltOnError = false)
	{
		$this->setFeed($feed);
		$this->pendingEntries = null;

		$reader = $this->getFeedReader();
		$feedData = $reader->getFeedData();
		if (!$feedData && empty($feedData['entries']))
		{
			// we've tried to fetch the feed data and failed we should trigger an update
			// of the last_fetch date to ensure we don't end up infinitely trying to fetch
			$this->updateLastFetch($feed);

			if ($haltOnError)
			{
				throw new PrintableException(\XF::phrase('there_was_problem_requesting_feed', [
					'message' => $reader->getException()
						? $reader->getException()->getMessage()
						: \XF::phrase('n_a'),
				]));
			}

			return null;
		}

		$this->setFeedData($feedData);
		$this->updateLastFetch($feed);

		return $feedData;
	}

	protected function updateLastFetch(Feed $feed, $time = null)
	{
		$feed->last_fetch = $time ?: time();
		$feed->save(false);
	}

	protected function setFeed(Feed $feed)
	{
		$this->feed = $feed;
	}

	protected function setFeedData(array $feedData)
	{
		$this->feedData = $feedData;
	}

	protected function getPendingEntries()
	{
		if ($this->pendingEntries === null)
		{
			$feedData = $this->feedData;

			$ids = [];
			foreach ($feedData['entries'] AS $id => $entry)
			{
				$uniqueEntryId = Str::substr($entry['id'], 0, 250);
				if (isset($ids[$uniqueEntryId]))
				{
					unset($feedData['entries'][$id]);
					continue;
				}

				$ids[$uniqueEntryId] = $id;
			}

			$existingEntries = $this->app->finder(FeedLogFinder::class)
				->where('feed_id', $this->feed->feed_id)
				->where('unique_id', array_keys($ids))
				->keyedBy('unique_id')
				->fetch();

			$this->pendingEntries = array_reverse(
				array_filter($feedData['entries'], function ($entry) use ($existingEntries)
				{
					return !isset($existingEntries[$entry['id']]);
				})
			);
		}

		return $this->pendingEntries;
	}

	public function countPendingEntries()
	{
		return count($this->getPendingEntries());
	}

	public function importEntries()
	{
		$entries = $this->getPendingEntries();

		foreach ($entries AS $entry)
		{
			$this->importEntry($entry);
		}
	}

	public function importEntry(array $entry)
	{
		$actionUser = $this->getEntryActionUser($entry);

		return \XF::asVisitor($actionUser, function () use ($entry, $actionUser)
		{
			$creator = $this->setupThreadCreate($entry);
			if (!$creator->validate($errors))
			{
				$error = reset($errors);
				$feedId = $this->feed->feed_id;
				\XF::logException(new \Exception("Error posting feed entry $entry[id] for feed $feedId: $error"));
				return false;
			}

			$db = $this->db();
			$db->beginTransaction();

			$creator->save();

			$thread = $creator->getThread();

			$this->logEntry($entry, $thread);

			$db->commit();

			$creator->sendNotifications();

			\XF::repository(ThreadWatchRepository::class)->autoWatchThread($thread, $actionUser, true);

			return true;
		});
	}

	protected function getEntryActionUser(array $entry)
	{
		$feed = $this->feed;

		if ($feed->user_id && $feed->User)
		{
			return $feed->User;
		}

		$userRepo = $this->repository(UserRepository::class);
		$strFormatter = $this->app->stringFormatter();

		if (!empty($entry['author']))
		{
			return $userRepo->getGuestUser($strFormatter->wholeWordTrim($entry['author'], 50, 0, ''));
		}
		else
		{
			return $userRepo->getGuestUser($strFormatter->wholeWordTrim($feed->title, 50, 0, ''));
		}
	}

	/**
	 * @return CreatorService
	 */
	protected function setupThreadCreate(array $entry)
	{
		$feed = $this->feed;

		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $feed->Forum);

		$creator->setIsAutomated();
		$creator->setContent($feed->getEntryTitle($entry), $feed->getEntryMessage($entry));
		$creator->setPrefix($feed->prefix_id);
		$creator->setDiscussionState($feed->discussion_visible ? 'visible' : 'moderated');
		$creator->setDiscussionOpen($feed->discussion_open);
		$creator->setSticky($feed->discussion_sticky);

		return $creator;
	}

	protected function logEntry(array $entry, Thread $thread)
	{
		$feed = $this->feed;

		$log = $this->em()->create(FeedLog::class);
		$log->feed_id = $feed->feed_id;
		$log->unique_id = Str::substr($entry['id'], 0, 250);
		$log->hash = md5($entry['id'] . $entry['title'] . $entry['content']);
		$log->thread_id = $thread->thread_id;
		$log->save(false, false);
	}

	/**
	 * @return ReaderService
	 */
	protected function getFeedReader()
	{
		return $this->service(ReaderService::class, $this->feed->url);
	}
}
