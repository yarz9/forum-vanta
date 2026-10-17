<?php

namespace XF\Repository;

use XF\Entity\LikedContent;
use XF\Entity\User;
use XF\Finder\LikedContentFinder;
use XF\Like\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class LikedContentRepository extends Repository
{
	/**
	 * @param string $contentType
	 * @param int $contentId
	 * @param int $userId
	 *
	 * @return LikedContent|null
	 */
	public function getLikeByContentAndLiker($contentType, $contentId, $userId)
	{
		return $this->finder(LikedContentFinder::class)->where([
			'content_type' => $contentType,
			'content_id' => $contentId,
			'like_user_id' => $userId,
		])->fetchOne();
	}

	/**
	 * @param string $contentType
	 * @param int $contentId
	 *
	 * @return Finder
	 */
	public function findContentLikes($contentType, $contentId)
	{
		return $this->finder(LikedContentFinder::class)
			->where([
				'content_type' => $contentType,
				'content_id' => $contentId,
			])->setDefaultOrder('like_date', 'DESC');
	}

	/**
	 * @param $likeUserId
	 *
	 * @return Finder
	 */
	public function findLikesByLikeUserId($likeUserId)
	{
		if ($likeUserId instanceof User)
		{
			$likeUserId = $likeUserId->user_id;
		}

		return $this->finder(LikedContentFinder::class)
			->where('like_user_id', $likeUserId)
			->setDefaultOrder('like_date');
	}

	public function toggleLike($contentType, $contentId, User $likeUser, $publish = true)
	{
		$reactionRepo = $this->getReactionRepo();

		return $reactionRepo->reactToContent(1, $contentType, $contentId, $likeUser, $publish, true);
	}

	public function insertLike($contentType, $contentId, User $likeUser, $publish = true)
	{
		$reactionRepo = $this->getReactionRepo();

		return $reactionRepo->insertReaction(1, $contentType, $contentId, $likeUser, $publish, true);
	}

	/**
	 * @return AbstractHandler[]
	 */
	public function getLikeHandlers()
	{
		return $this->getReactionRepo()->getReactionHandlers(true);
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 */
	public function getLikeHandler($type, $throw = false)
	{
		return $this->getReactionRepo()->getReactionHandler($type, $throw, true);
	}

	/**
	 * @param AbstractCollection<LikedContent> $likes
	 */
	public function addContentToLikes($likes)
	{
		$this->getReactionRepo()->addContentToReactions($likes);
	}

	public function rebuildContentLikeCache($contentType, $contentId, $throw = true)
	{
		return $this->getReactionRepo()->rebuildContentReactionCache($contentType, $contentId, true, $throw);
	}

	public function recalculateLikeIsCounted($contentType, $contentIds, $updateLikeCount = true)
	{
		$this->getReactionRepo()->recalculateReactionIsCounted($contentType, $contentIds, $updateLikeCount, true);
	}

	public function fastUpdateLikeIsCounted($contentType, $contentIds, $newValue, $updateLikeCount = true)
	{
		$this->getReactionRepo()->fastUpdateReactionIsCounted($contentType, $contentIds, $newValue, $updateLikeCount);
	}

	public function fastDeleteLikes($contentType, $contentIds, $updateLikeCount = true)
	{
		$this->getReactionRepo()->fastDeleteReactions($contentType, $contentIds, $updateLikeCount);
	}

	public function getUserLikeCount($userId)
	{
		return $this->getReactionRepo()->getUserReactionScore($userId);
	}

	/**
	 * @param $userId
	 *
	 * @return Finder
	 */
	public function findUserLikes($userId)
	{
		$reactionFinder = $this->getReactionRepo()->findUserReactions($userId);

		$reactionFinder->where('reaction_id', 1);

		return $reactionFinder;
	}

	/**
	 * @return Repository|ReactionRepository
	 */
	protected function getReactionRepo()
	{
		return $this->repository(ReactionRepository::class);
	}
}
