<?php

namespace XF\Pub\View\Forum;

use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\View;
use XF\Pub\View\FeedHelper;

class Rss extends View
{
	/**
	 * @return string
	 */
	public function renderRss()
	{
		/** @var Forum $forum */
		$forum = $this->params['forum'];
		/** @var AbstractCollection<Thread> $threads */
		$threads = $this->params['threads'];
		/** @var string $order */
		$order = $this->params['order'];

		$feed = $this->createFeed($forum);

		foreach ($threads AS $thread)
		{
			$feed->addEntry($this->createEntry($feed, $thread, $order));
		}

		return $this->exportFeed($feed);
	}

	protected function createFeed(?Forum $forum): Feed
	{
		$feed = new Feed();

		$router = \XF::app()->router('public');

		if ($forum)
		{
			$title = $forum->title;
			$description = $forum->description;
			$feedLink = $router->buildLink('canonical:forums/index.rss', $forum);
			$link = $router->buildLink('canonical:forums', $forum);
		}
		else
		{
			$options = \XF::options();
			$title = $options->boardTitle;
			$description = $options->boardDescription;
			$feedLink = $router->buildLink('canonical:forums/index.rss', '-');
			$link = $router->buildLink('canonical:forums');
		}

		FeedHelper::setupFeed($feed, $title, $description, $feedLink, $link);

		return $feed;
	}

	protected function createEntry(
		Feed $feed,
		Thread $thread,
		string $order
	): Entry
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
