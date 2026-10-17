<?php

namespace XF\Pub\View\SearchForum;

use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use XF\Entity\SearchForum;
use XF\Entity\Thread;
use XF\Mvc\View;
use XF\Pub\View\FeedHelper;

class Rss extends View
{
	/**
	 * @return string
	 */
	public function renderRss()
	{
		/** @var SearchForum $searchForum */
		$searchForum = $this->params['searchForum'];
		/** @var AbstractCollection<Thread> $threads */
		$threads = $this->params['threads'];

		$feed = $this->createFeed($searchForum);

		foreach ($threads AS $thread)
		{
			$feed->addEntry($this->createEntry($feed, $thread));
		}

		return $this->exportFeed($feed);
	}

	protected function createFeed(SearchForum $searchForum): Feed
	{
		$feed = new Feed();

		$router = \XF::app()->router('public');

		FeedHelper::setupFeed(
			$feed,
			$searchForum->title,
			$searchForum->description,
			$router->buildLink(
				'canonical:search-forums/index.rss',
				$searchForum
			),
			$router->buildLink('canonical:search-forums', $searchForum)
		);

		return $feed;
	}

	protected function createEntry(Feed $feed, Thread $thread): Entry
	{
		$entry = $feed->createEntry();

		FeedHelper::setupEntryForThread($entry, $thread);

		return $entry;
	}

	protected function exportFeed(Feed $feed): string
	{
		return $feed->orderByDate()->export('rss', true);
	}
}
