<?php

namespace XF\Import\Data;

use XF\Entity\Reaction;
use XF\Repository\LikedContentRepository;

/**
 * @mixin \XF\Entity\LikedContent
 */
class LikedContent extends AbstractEmulatedData
{
	protected $columnMap = [
		'like_id' => 'reaction_content_id',
		'like_user_id' => 'reaction_user_id',
		'like_date' => 'reaction_date',
	];

	public function getImportType()
	{
		return 'liked_content';
	}

	public function getEntityShortName()
	{
		return 'XF:LikedContent';
	}

	protected function write($oldId)
	{
		// traditional "likes" are always reaction ID 1
		$this->ee->set('reaction_id', 1);

		return parent::write($oldId);
	}

	protected function postSave($oldId, $newId)
	{
		/** @var Reaction $reaction */
		$reaction = $this->em()->find(Reaction::class, 1); // like
		$reactionScore = $reaction->reaction_score;

		if ($this->is_counted && $this->content_user_id)
		{
			$this->db()->query("
				UPDATE xf_user
				SET reaction_score = reaction_score + ?
				WHERE user_id = ?
			", [$reactionScore, $this->content_user_id]);
		}

		$this->app()->repository(LikedContentRepository::class)->rebuildContentLikeCache(
			$this->content_type,
			$this->content_id,
			false
		);
	}
}
