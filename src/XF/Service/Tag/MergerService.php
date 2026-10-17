<?php

namespace XF\Service\Tag;

use XF\App;
use XF\Entity\Tag;
use XF\Job\TagRecache;
use XF\Repository\TagRepository;
use XF\Service\AbstractService;

class MergerService extends AbstractService
{
	/**
	 * @var Tag
	 */
	protected $target;

	public function __construct(App $app, Tag $target)
	{
		parent::__construct($app);

		$this->target = $target;
	}

	public function getTarget()
	{
		return $this->target;
	}

	public function setTarget(Tag $tag)
	{
		$this->target = $tag;
	}

	public function merge(Tag $source)
	{
		$db = $this->db();

		$targetTagId = $this->target->tag_id;
		$sourceTagId = $source->tag_id;

		if ($targetTagId == $sourceTagId)
		{
			throw new \InvalidArgumentException("May not merge a tag with itself");
		}

		$db->beginTransaction();

		$db->query("
			UPDATE IGNORE xf_tag_content
			SET tag_id = ?
			WHERE tag_id = ?
		", [$targetTagId, $sourceTagId]);

		// this handles cases where the content already had the target tag
		$db->query("DELETE FROM xf_tag_content WHERE tag_id = ?", $sourceTagId);

		$db->query("DELETE FROM xf_tag_result_cache WHERE tag_id = ?", $targetTagId);

		$source->delete();

		/** @var TagRepository $tagRepo */
		$tagRepo = $this->repository(TagRepository::class);
		$tagRepo->recalculateTagUsageCache($targetTagId);

		$db->commit();

		$this->app->jobManager()->enqueueUnique('tagUpdate' . $targetTagId, TagRecache::class, [
			'tagId' => $targetTagId,
		]);
	}
}
