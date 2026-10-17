<?php

namespace XF\Sitemap;

use XF\Entity\Tag;
use XF\Finder\TagFinder;

/**
 * @extends AbstractHandler<Tag>
 */
class TagHandler extends AbstractHandler
{
	public function getRecords($start)
	{
		$app = $this->app;

		$tagIds = $this->getIds('xf_tag', 'tag_id', $start);

		$tagFinder = $app->finder(TagFinder::class);
		$tags = $tagFinder
			->where('tag_id', $tagIds)
			->order('tag_id')
			->fetch();

		return $tags;
	}

	public function getEntry($record)
	{
		$url = $this->app->router('public')->buildLink('canonical:tags', $record);
		return Entry::create($url, [
			'lastmod' => $record->last_use_date,
		]);
	}

	public function basePermissionCheck()
	{
		if (parent::basePermissionCheck())
		{
			return $this->app->options()->enableTagging;
		}
		else
		{
			return false;
		}
	}

	public function isIncluded($record)
	{
		return ($record->use_count > 0);
	}
}
