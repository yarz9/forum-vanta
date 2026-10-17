<?php

namespace XF\Repository;

use XF\Entity\ProfilePost;
use XF\Entity\ProfilePostComment;
use XF\Entity\User;
use XF\Finder\ProfilePostCommentFinder;
use XF\Finder\ProfilePostFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;

use function intval;

class ProfilePostRepository extends Repository
{
	public function findProfilePostsOnProfile(User $user, array $limits = [])
	{
		/** @var ProfilePostFinder $finder */
		$finder = $this->finder(ProfilePostFinder::class);
		$finder
			->onProfile($user, $limits)
			->order('post_date', 'DESC');

		return $finder;
	}

	/**
	 * @param User $user
	 * @param $newerThan
	 * @param array $limits
	 *
	 * @return ProfilePostFinder
	 */
	public function findNewestProfilePostsOnProfile(User $user, $newerThan, array $limits = [])
	{
		/** @var ProfilePostFinder $finder */
		$finder = $this->findNewestProfilePosts($newerThan)
			->onProfile($user, $limits);

		return $finder;
	}

	/**
	 * @param $newerThan
	 *
	 * @return ProfilePostFinder
	 */
	public function findNewestProfilePosts($newerThan)
	{
		/** @var ProfilePostFinder $finder */
		$finder = $this->finder(ProfilePostFinder::class);
		$finder
			->newerThan($newerThan)
			->order('post_date', 'DESC');

		return $finder;
	}

	/**
	 * @param ProfilePost $profilePost
	 * @param array $limits
	 *
	 * @return ProfilePostCommentFinder
	 */
	public function findProfilePostComments(ProfilePost $profilePost, array $limits = [])
	{
		/** @var ProfilePostCommentFinder $commentFinder */
		$commentFinder = $this->finder(ProfilePostCommentFinder::class);
		$commentFinder->setDefaultOrder('comment_date');
		$commentFinder->forProfilePost($profilePost, $limits);

		return $commentFinder;
	}

	public function findNewestCommentsForProfilePost(ProfilePost $profilePost, $newerThan, array $limits = [])
	{
		/** @var ProfilePostCommentFinder $commentFinder */
		$commentFinder = $this->finder(ProfilePostCommentFinder::class);
		$commentFinder
			->setDefaultOrder('comment_date', 'DESC')
			->forProfilePost($profilePost, $limits)
			->newerThan($newerThan);

		return $commentFinder;
	}

	/**
	 * @param AbstractCollection<ProfilePost>|array<int, ProfilePost> $profilePosts
	 * @param bool $skipUnfurlRecrawl
	 *
	 * @return AbstractCollection<ProfilePost>
	 */
	public function addCommentsToProfilePosts($profilePosts, $skipUnfurlRecrawl = false)
	{
		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = $this->repository(AttachmentRepository::class);

		$commentFinder = $this->finder(ProfilePostCommentFinder::class);

		$visitor = \XF::visitor();

		$ids = [];
		foreach ($profilePosts AS $profilePostId => $profilePost)
		{
			$commentIds = $profilePost->latest_comment_ids;
			foreach ($commentIds AS $commentId => $state)
			{
				$commentId = intval($commentId);

				switch ($state[0])
				{
					case 'visible':
						$ids[] = $commentId;
						break;

					case 'moderated':
						if ($profilePost->canViewModeratedComments())
						{
							// can view all moderated comments
							$ids[] = $commentId;
						}
						else if ($visitor->user_id && $visitor->user_id == $state[1])
						{
							// can view your own moderated comments
							$ids[] = $commentId;
						}
						break;

					case 'deleted':
						if ($profilePost->canViewDeletedComments())
						{
							$ids[] = $commentId;

							$commentFinder->with('DeletionLog');
						}
						break;
				}
			}
		}

		if ($ids)
		{
			$commentFinder->with('full');

			$comments = $commentFinder
				->where('profile_post_comment_id', $ids)
				->order('comment_date')
				->fetch();

			/** @var UnfurlRepository $unfurlRepo */
			$unfurlRepo = $this->repository(UnfurlRepository::class);
			$unfurlRepo->addUnfurlsToContent($comments, $skipUnfurlRecrawl);

			/** @var EmbedResolverRepository $embedRepo */
			$embedRepo = $this->repository(EmbedResolverRepository::class);
			$embedRepo->addEmbedsToContent($comments);

			$attachmentRepo->addAttachmentsToContent($comments, 'profile_post_comment');

			$comments = $comments->groupBy('profile_post_id');

			foreach ($profilePosts AS $profilePostId => $profilePost)
			{
				$profilePostComments = $comments[$profilePostId] ?? [];
				$profilePostComments = $this->em->getBasicCollection($profilePostComments)
					->filterViewable()
					->slice(-3, 3);

				$profilePost->setLatestComments($profilePostComments->toArray());
			}
		}

		return $profilePosts;
	}

	public function addCommentsToProfilePost(ProfilePost $profilePost)
	{
		$id = $profilePost->profile_post_id;
		$result = $this->addCommentsToProfilePosts([$id => $profilePost]);
		return $result[$id];
	}

	public function getLatestCommentCache(ProfilePost $profilePost)
	{
		$comments = $this->finder(ProfilePostCommentFinder::class)
			->where('profile_post_id', $profilePost->profile_post_id)
			->order('comment_date', 'DESC')
			->limit(20)
			->fetch();

		$visCount = 0;
		$latestComments = [];

		/** @var ProfilePostComment $comment */
		foreach ($comments AS $commentId => $comment)
		{
			if ($comment->message_state == 'visible')
			{
				$visCount++;
			}

			$latestComments[$commentId] = [$comment->message_state, $comment->user_id];

			if ($visCount === 3)
			{
				break;
			}
		}

		return array_reverse($latestComments, true);
	}

	public function sendModeratorActionAlert(ProfilePost $profilePost, $action, $reason = '', array $extra = [])
	{
		if (!$profilePost->user_id || !$profilePost->User)
		{
			return false;
		}

		$router = $this->app()->router('public');

		$extra = array_merge([
			'profileUserId' => $profilePost->profile_user_id,
			'profileUser' => $profilePost->ProfileUser ? $profilePost->ProfileUser->username : '',
			'profileLink' => $router->buildLink('nopath:members', $profilePost->ProfileUser),
			'link' => $router->buildLink('nopath:profile-posts', $profilePost),
			'reason' => $reason,
		], $extra);

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$profilePost->User,
			0,
			'',
			'user',
			$profilePost->user_id,
			"profile_post_{$action}",
			$extra
		);

		return true;
	}

	public function sendCommentModeratorActionAlert(ProfilePostComment $comment, $action, $reason = '', array $extra = [])
	{
		if (!$comment->user_id || !$comment->User)
		{
			return false;
		}

		/** @var ProfilePost $profilePost */
		$profilePost = $comment->ProfilePost;
		if (!$profilePost)
		{
			return false;
		}

		$router = $this->app()->router('public');

		$extra = array_merge([
			'profileUserId' => $profilePost->profile_user_id,
			'profileUser' => $profilePost->ProfileUser ? $profilePost->ProfileUser->username : '',
			'postUserId' => $profilePost->user_id,
			'postUser' => $profilePost->User ? $profilePost->User->username : '',
			'link' => $router->buildLink('nopath:profile-posts/comments', $comment),
			'profileLink' => $router->buildLink('nopath:members', $profilePost->ProfileUser),
			'profilePostLink' => $router->buildLink('nopath:profile-posts', $profilePost),
			'reason' => $reason,
		], $extra);

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$comment->User,
			0,
			'',
			'user',
			$comment->user_id,
			"profile_post_comment_{$action}",
			$extra
		);

		return true;
	}
}
