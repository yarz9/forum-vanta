<?php

namespace XF\Finder;

use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\NewsFeed> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\NewsFeed> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\NewsFeed|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\NewsFeed>
 */
class NewsFeedFinder extends Finder
{
	public function beforeFeedId($feedId)
	{
		if ($feedId)
		{
			$this->where('news_feed_id', '<', $feedId);
		}

		return $this;
	}

	public function applyPrivacyChecks(?User $viewingUser = null)
	{
		if (!$viewingUser)
		{
			$viewingUser = \XF::visitor();
		}

		if ($viewingUser->canBypassUserPrivacy())
		{
			// no limits
			return $this;
		}

		if ($viewingUser->user_id)
		{
			$privacyConditions = [];
			$privacyConditions[] = ['user_id', $viewingUser->user_id];
			$privacyConditions[] = ['user_id', 0];
			$privacyConditions[] = ['User.Privacy.allow_receive_news_feed', ['everyone', 'members']];
			$privacyConditions[] = [
				['User.Privacy.allow_receive_news_feed', 'followed'],
				['User.Following|' . $viewingUser->user_id . '.user_id', '!=', null],
			];

			$this->whereOr($privacyConditions);
		}
		else
		{
			$this->whereOr(
				['user_id' => 0],
				['User.Privacy.allow_receive_news_feed' => 'everyone']
			);
		}

		return $this;
	}

	public function forUser(User $user)
	{
		if ($user->user_id)
		{
			$following = $user->Profile->following ?: [];

			$this->where('user_id', $following);
		}
		else
		{
			$this->whereImpossible();
		}

		return $this;
	}

	public function byUser(User $user)
	{
		$this->where('user_id', $user->user_id);

		return $this;
	}
}
