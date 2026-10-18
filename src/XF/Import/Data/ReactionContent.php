<?php

namespace XF\Import\Data;

use XF\Entity\Reaction;
use XF\Repository\ReactionRepository;

/**
 * @mixin \XF\Entity\ReactionContent
 */
class ReactionContent extends AbstractEmulatedData
{
	protected $allowRetainIds = false;

	public function getImportType()
	{
		return 'reaction_content';
	}

	public function getEntityShortName()
	{
		return 'XF:ReactionContent';
	}

	public function setReactionId($reactionId)
	{
		$this->set('reaction_id', $reactionId);
	}

	protected function preSave($oldId)
	{
		if (!$this->get('reaction_id'))
		{
			throw new \LogicException("Must set a reaction ID");
		}
	}

	protected function postSave($oldId, $newId)
	{
		/** @var Reaction $reaction */
		$reaction = $this->em()->find(Reaction::class, $this->get('reaction_id'));
		if ($reaction)
		{
			$reactionScore = $reaction->reaction_score;

			if ($this->is_counted && $this->content_user_id)
			{
				$this->db()->query("
					UPDATE xf_user
					SET reaction_score = reaction_score + ?
					WHERE user_id = ?
				", [$reactionScore, $this->content_user_id]);
			}

			$this->app()->repository(ReactionRepository::class)->rebuildContentReactionCache(
				$this->content_type,
				$this->content_id,
				false
			);
		}
	}
}
