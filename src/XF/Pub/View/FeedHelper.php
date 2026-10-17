<?php

namespace XF\Pub\View;

use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use XF\Entity\Thread;

use function strlen;

class FeedHelper
{
	public static function setupFeed(
		Feed $feed,
		string $title,
		string $description,
		string $feedLink,
		string $link = ''
	)
	{
		$app = \XF::app();
		$options = $app->options();
		$router = $app->router('public');

		$link = $link ?: $router->buildLink('canonical:index');
		$title = $title ?: $link;
		$description = $description ?: $title; // required in rss 2.0 spec

		$feed->setEncoding('utf-8')
			->setTitle($title)
			->setDescription($description)
			->setLink($link)
			->setFeedLink($feedLink, 'rss')
			->setDateModified(\XF::$time)
			->setLastBuildDate(\XF::$time)
			->setGenerator($options->boardTitle);

		$languageCode = \XF::language()->getLanguageCode();
		if ($languageCode)
		{
			$feed->setLanguage(\XF::language()->getLanguageCode());
		}
	}

	public static function setupEntryForThread(Entry $entry, Thread $thread)
	{
		$app = \XF::app();
		$options = $app->options();
		$router = $app->router('public');

		$link = $router->buildLink('canonical:threads', $thread);

		$author = [
			'name' => $thread->User
				? $thread->User->username
				: $thread->username,
			'email' => 'invalid@example.com',
		];
		if ($thread->User)
		{
			$author['uri'] = $router->buildLink(
				'canonical:members',
				$thread->User
			);
		}

		$entry
			->setId((string) $thread->thread_id)
			->setTitle($thread->title)
			->setLink($link)
			->setDateCreated($thread->post_date)
			->setDateModified($thread->last_post_date)
			->addAuthor($author);

		$threadForum = $thread->Forum;
		if ($threadForum)
		{
			$threadForumLink = $router->buildLink(
				'canonical:forums',
				$threadForum
			);
			$entry->addCategory([
				'term' => $threadForum->title,
				'scheme' => $threadForumLink,
			]);
		}

		$firstPost = $thread->FirstPost;
		$maxLength = $options->discussionRssContentLength;
		if ($maxLength && $firstPost && $firstPost->message)
		{
			$bbCodeParser = $app->bbCode()->parser();
			$bbCodeRules = $app->bbCode()->rules('post:rss');

			$bbCodeCleaner = $app->bbCode()->renderer('bbCodeClean');
			$bbCodeRenderer = $app->bbCode()->renderer('html');

			$stringFormatter = $app->stringFormatter();

			$snippet = $bbCodeCleaner->render(
				$stringFormatter->wholeWordTrimBbCode(
					$firstPost->message,
					$maxLength
				),
				$bbCodeParser,
				$bbCodeRules
			);
			if ($snippet != $firstPost->message)
			{
				$readMore = \XF::phrase('read_more');
				$snippet .= "\n\n[URL='{$link}']{$readMore}[/URL]";
			}

			$renderOptions = $firstPost->getBbCodeRenderOptions(
				'post:rss',
				'html'
			);
			$renderOptions['noProxy'] = true;
			$renderOptions['lightbox'] = false;

			$content = trim($bbCodeRenderer->render(
				$snippet,
				$bbCodeParser,
				$bbCodeRules,
				$renderOptions
			));
			if (strlen($content))
			{
				$entry->setContent($content);
			}
		}

		if ($thread->reply_count)
		{
			$entry->setCommentCount($thread->reply_count);
		}
	}
}
