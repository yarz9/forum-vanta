<?php

namespace XF\Finder;

use XF\Entity\ProfilePost;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ProfilePostComment> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ProfilePostComment> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ProfilePostComment|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ProfilePostComment>
 */
class ProfilePostCommentFinder extends Finder
{
	public function forProfilePost(ProfilePost $profilePost, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('profile_post_id', $profilePost->profile_post_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksForProfilePost($profilePost, $limits['allowOwnPending']);
		}

		return $this;
	}

	public function applyVisibilityChecksForProfilePost(ProfilePost $profilePost, $allowOwnPending = true)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($profilePost->canViewDeletedComments())
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($profilePost->canViewModeratedComments())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'message_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['message_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	public function newerThan($date)
	{
		$this->where('comment_date', '>', $date);

		return $this;
	}
}
