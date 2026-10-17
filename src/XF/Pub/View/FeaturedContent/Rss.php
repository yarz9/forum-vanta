<?php

namespace XF\Pub\View\FeaturedContent;

use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use XF\Entity\FeaturedContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\View;
use XF\Pub\View\FeedHelper;

class Rss extends View
{
	public function renderRss(): string
	{
		/** @var AbstractCollection<FeaturedContent> $features */
		$features = $this->params['features'];

		$feed = $this->createFeed();

		foreach ($features AS $feature)
		{
			$feed->addEntry($this->createEntry($feed, $feature));
		}

		return $this->exportFeed($feed);
	}

	protected function createFeed(): Feed
	{
		$feed = new Feed();

		$router = \XF::app()->router('public');

		FeedHelper::setupFeed(
			$feed,
			\XF::phrase('featured_content'),
			'',
			$router->buildLink('canonical:featured/index.rss'),
			$router->buildLink('canonical:featured')
		);

		return $feed;
	}

	protected function createEntry(Feed $feed, FeaturedContent $feature): Entry
	{
		$entry = $feed->createEntry();

		$author = [
			'name' => $feature->ContentUser
				? $feature->ContentUser->username
				: $feature->content_username,
			'email' => 'invalid@example.com',
		];
		if ($feature->ContentUser)
		{
			$router = \XF::app()->router('public');
			$author['uri'] = $router->buildLink(
				'canonical:members',
				$feature->ContentUser
			);
		}

		$entry
			->setId((string) $feature->featured_content_id)
			->setTitle($feature->title)
			->setLink($feature->getContentLink(true))
			->setDateCreated($feature->feature_date)
			->addAuthor($author);

		if ($feature->snippet)
		{
			$entry->setContent($feature->snippet);
		}

		return $entry;
	}

	protected function exportFeed(Feed $feed): string
	{
		return $feed->orderByDate()->export('rss', true);
	}
}
