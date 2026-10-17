<?php

namespace XF\Finder;

use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ProfilePost> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ProfilePost> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ProfilePost|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ProfilePost>
 */
class ProfilePostFinder extends Finder
{
	public function onProfile(User $user, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('profile_user_id', $user->user_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksForProfile($user, $limits['allowOwnPending']);
		}

		$this->with('full');

		return $this;
	}

	public function applyVisibilityChecksForProfile(User $user, $allowOwnPending = true)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($user->canViewDeletedPostsOnProfile())
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($user->canViewModeratedPostsOnProfile())
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
		$this->where('post_date', '>', $date);

		return $this;
	}
}
