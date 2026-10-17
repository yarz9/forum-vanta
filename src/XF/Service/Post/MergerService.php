<?php

namespace XF\Service\Post;

use XF\App;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Job\SearchIndex;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\ActivityLogRepository;
use XF\Repository\ContentVoteRepository;
use XF\Repository\EditHistoryRepository;
use XF\Repository\PostRepository;
use XF\Repository\ReactionRepository;
use XF\Repository\ThreadRepository;
use XF\Service\AbstractService;
use XF\Service\ModerationAlertSendableTrait;

use function array_key_exists, is_array;

class MergerService extends AbstractService
{
	use ModerationAlertSendableTrait;

	/**
	 * @var Post
	 */
	protected $target;

	protected $originalTargetMessage;

	/**
	 * @var PreparerService
	 */
	protected $postPreparer;

	protected $alert = false;
	protected $alertReason = '';

	protected $log = true;

	/**
	 * @var Thread[]
	 */
	protected $sourceThreads = [];

	/**
	 * @var Post[]
	 */
	protected $sourcePosts = [];

	/**
	 * @var array
	 */
	protected $movedReactions = [];

	public function __construct(App $app, Post $target)
	{
		parent::__construct($app);

		$this->target = $target;
		$this->originalTargetMessage = $target->message;
		$this->postPreparer = $this->service(PreparerService::class, $this->target);
	}

	public function getTarget()
	{
		return $this->target;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function setLog($log)
	{
		$this->log = (bool) $log;
	}

	public function setMessage($message, $format = true)
	{
		return $this->postPreparer->setMessage($message, $format);
	}

	public function merge($sourcePostsRaw)
	{
		if ($sourcePostsRaw instanceof AbstractCollection)
		{
			$sourcePostsRaw = $sourcePostsRaw->toArray();
		}
		else if ($sourcePostsRaw instanceof Post)
		{
			$sourcePostsRaw = [$sourcePostsRaw];
		}
		else if (!is_array($sourcePostsRaw))
		{
			throw new \InvalidArgumentException('Posts must be provided as collection, array or entity');
		}

		if (!$sourcePostsRaw)
		{
			return false;
		}

		if ($this->alert)
		{
			$contentIds = [$this->target->Thread->node_id];
			$permissionCombinationIds = [];
			foreach ($sourcePostsRaw AS $sourcePost)
			{
				/** @var Post $sourcePost */
				if (!$sourcePost->user_id || !$sourcePost->User)
				{
					continue;
				}

				$contentIds[] = $sourcePost->Thread->node_id;
				$permissionCombinationIds[] = $sourcePost->User->permission_combination_id;
			}

			static::cacheContentPermissions(
				'node',
				$contentIds,
				$permissionCombinationIds
			);

			foreach ($sourcePostsRaw AS $sourcePost)
			{
				/** @var Post $sourcePost */
				$this->wasVisibleForAlert[$sourcePost->post_id] = $this->isContentVisibleToContentAuthor(
					$sourcePost,
					$sourcePost
				);
				$this->isVisibleForAlert[$sourcePost->post_id] = $this->isContentVisibleToContentAuthor(
					$this->target,
					$sourcePost
				);
			}
		}

		$db = $this->db();

		/** @var Post[] $sourcePosts */
		$sourcePosts = [];

		/** @var Thread[] $sourceThreads */
		$sourceThreads = [];

		foreach ($sourcePostsRaw AS $sourcePost)
		{
			$sourcePost->setOption('log_moderator', false);
			$sourcePosts[$sourcePost->post_id] = $sourcePost;

			/** @var Thread $sourceThread */
			$sourceThread = $sourcePost->Thread;
			if (!isset($sourceThreads[$sourceThread->thread_id]))
			{
				$sourceThread->setOption('log_moderator', false);
				$sourceThreads[$sourceThread->thread_id] = $sourceThread;
			}
		}

		$this->sourceThreads = $sourceThreads;
		$this->sourcePosts = $sourcePosts;

		$target = $this->target;
		$target->setOption('log_moderator', false);

		$db->beginTransaction();

		$this->moveDataToTarget();
		$this->updateTargetData();
		$this->updateSourceData();
		$this->updateActivityLog();
		$this->updateUserCounters();

		if ($this->alert)
		{
			$this->sendAlert();
		}

		$this->finalActions();

		$target->save();

		$this->cleanupActions();

		$db->commit();

		return true;
	}

	protected function moveDataToTarget()
	{
		$db = $this->db();
		$target = $this->target;

		$sourcePosts = $this->sourcePosts;
		$sourcePostIds = array_keys($sourcePosts);
		$sourceIdsQuoted = $db->quote($sourcePostIds);

		$this->repository(ContentVoteRepository::class)->moveVotesBetweenContent($target, $sourcePosts);

		$sourceReactions = $db->fetchAllKeyed(
			"SELECT *
				FROM xf_reaction_content AS reaction_content
				INNER JOIN xf_reaction AS reaction
					ON (reaction.reaction_id = reaction_content.reaction_id)
				WHERE content_type = 'post'
				AND content_id IN ({$sourceIdsQuoted})",
			'reaction_content_id'
		);
		if ($sourceReactions)
		{
			$updateReactions = [];
			$targetReactions = $db->fetchAllKeyed(
				"SELECT *
					FROM xf_reaction_content
					WHERE content_type = 'post'
					AND content_id = ?",
				'reaction_user_id',
				$target->post_id
			);

			foreach ($sourceReactions AS $reactionContentId => $reactionContent)
			{
				$reactionUserId = $reactionContent['reaction_user_id'];
				if (
					($reactionUserId && $reactionUserId === $target->user_id) ||
					isset($targetReactions[$reactionUserId])
				)
				{
					// reaction is from the content user
					// or the user has already reacted to the content
					// these will be cleaned up when the source content is deleted
					unset($sourceReactions[$reactionContentId]);
					continue;
				}

				$updateReactions[] = $reactionContentId;
				$targetReactions[$reactionUserId] = $reactionContent;
			}

			$this->movedReactions = $sourceReactions;

			if ($updateReactions)
			{
				$updateReactionsQuoted = $db->quote($updateReactions);
				$targetReactionsCount = (
					$target->message_state == 'visible' &&
					$target->Thread->discussion_state == 'visible'
				);

				$db->update(
					'xf_reaction_content',
					[
						'content_id' => $target->post_id,
						'content_user_id' => $target->user_id,
						'is_counted' => $targetReactionsCount ? 1 : 0,
					],
					"reaction_content_id IN ({$updateReactionsQuoted})"
				);
			}
		}

		$rows = $db->update(
			'xf_attachment',
			['content_id' => $target->post_id],
			"content_id IN ($sourceIdsQuoted) AND content_type = 'post'"
		);

		$target->attach_count += $rows;

		$db->update(
			'xf_bookmark_item',
			[
				'content_type' => 'post',
				'content_id' => $target->post_id,
			],
			"content_id IN ($sourceIdsQuoted) AND content_type = 'post'",
			[],
			'IGNORE'
		);

		foreach ($sourcePosts AS $sourcePost)
		{
			$sourcePost->delete();
		}
	}

	protected function updateTargetData()
	{
		/** @var Thread $targetThread */
		$targetThread = $this->target->Thread;

		$targetThread->rebuildCounters();
		$targetThread->save();

		if ($this->target->post_id == $targetThread->first_post_id && $this->target->message_state != 'visible')
		{
			// first post of the thread must always be visible
			$this->target->message_state = 'visible';
		}
	}

	protected function updateSourceData()
	{
		/** @var ThreadRepository $threadRepo */
		$threadRepo = $this->repository(ThreadRepository::class);

		foreach ($this->sourceThreads AS $sourceThread)
		{
			$sourceThread->rebuildCounters();

			$sourceThread->save(); // has to be saved for the delete to work (if needed).

			if (array_key_exists($sourceThread->first_post_id, $this->sourcePosts) && $sourceThread->reply_count == 0)
			{
				$sourceThread->delete(); // first post has been moved out, no other replies, thread now empty
			}
			else
			{
				$threadRepo->rebuildThreadPostPositions($sourceThread->thread_id);
				$threadRepo->rebuildThreadUserPostCounters($sourceThread->thread_id);
			}

			$sourceThread->Forum->rebuildCounters();
			$sourceThread->Forum->save();
		}
	}

	protected function updateActivityLog(): void
	{
		$rebuildReplies = [];
		$rebuildReactions = [];

		if ($this->target->isFirstPost())
		{
			$rebuildReactions[$this->target->thread_id] = $this->target->Thread;
		}

		foreach ($this->sourcePosts AS $sourcePost)
		{
			$rebuildReplies[$sourcePost->thread_id] = $sourcePost->Thread;

			if ($sourcePost->isFirstPost())
			{
				$rebuildReactions[$sourcePost->thread_id] = $sourcePost->Thread;
			}
		}

		$activityLogRepo = $this->repository(ActivityLogRepository::class);

		foreach ($rebuildReplies AS $thread)
		{
			$activityLogRepo->rebuildReplyMetrics($thread);
		}

		foreach ($rebuildReactions AS $thread)
		{
			$activityLogRepo->rebuildReactionMetrics($thread);
		}
	}

	protected function updateUserCounters()
	{
		$target = $this->target;

		$userReactionCountAdjust = [];
		$targetReactionsCount = (
			$target->message_state == 'visible' &&
			$target->Thread->discussion_state == 'visible'
		);

		foreach ($this->movedReactions AS $reactionContentId => $reactionContent)
		{
			$sourceReactionsCount = $reactionContent['is_counted'];
			$sourceUserId = $reactionContent['content_user_id'];
			$targetUserId = $target->user_id;
			$reactionScore = $reactionContent['reaction_score'];

			if ($sourceReactionsCount && $sourceUserId)
			{
				if (!isset($userReactionCountAdjust[$sourceUserId]))
				{
					$userReactionCountAdjust[$sourceUserId] = 0;
				}

				$userReactionCountAdjust[$sourceUserId] -= $reactionScore;
			}

			if ($targetReactionsCount && $targetUserId)
			{
				if (!isset($userReactionCountAdjust[$targetUserId]))
				{
					$userReactionCountAdjust[$targetUserId] = 0;
				}

				$userReactionCountAdjust[$targetUserId] += $reactionScore;
			}
		}

		foreach ($userReactionCountAdjust AS $userId => $adjust)
		{
			if (!$adjust)
			{
				continue;
			}

			$this->db()->query(
				'UPDATE xf_user
					SET reaction_score = reaction_score + ?
					WHERE user_id = ?',
				[$adjust, $userId]
			);
		}
	}

	protected function sendAlert()
	{
		/** @var PostRepository $postRepo */
		$postRepo = $this->repository(PostRepository::class);

		$alerted = [];
		foreach ($this->sourcePosts AS $sourcePost)
		{
			if (isset($alerted[$sourcePost->user_id]))
			{
				continue;
			}

			if (
				$sourcePost->message_state == 'visible'
				&& $sourcePost->user_id != \XF::visitor()->user_id
				&& (
					!empty($this->wasVisibleForAlert[$sourcePost->post_id])
					|| !empty($this->isVisibleForAlert[$sourcePost->post_id])
				)
			)
			{
				$postRepo->sendModeratorActionAlert($sourcePost, 'merge', $this->alertReason);
				$alerted[$sourcePost->user_id] = true;
			}
		}
	}

	protected function finalActions()
	{
		$target = $this->target;


		$preEditMergeMessage = $this->originalTargetMessage;
		foreach ($this->sourcePosts AS $s)
		{
			$preEditMergeMessage .= "\n\n" . $s->message;
		}
		$preEditMergeMessage = trim($preEditMergeMessage);

		$options = $this->app->options();
		if ($options->editLogDisplay['enabled'] && $this->log && $target->message != $preEditMergeMessage)
		{
			$target->last_edit_date = \XF::$time;
			$target->last_edit_user_id = \XF::visitor()->user_id;
		}

		if ($options->editHistory['enabled'])
		{
			$visitor = \XF::visitor();
			$ip = $this->app->request()->getIp();

			/** @var EditHistoryRepository $editHistoryRepo */
			$editHistoryRepo = $this->app->repository(EditHistoryRepository::class);

			// Log an edit history record for the target post's original message then log a further record
			// for the pre-merge result of all the source and target messages. These two entries should ensure
			// there is no context loss as a result of merging a series of posts.
			$editHistoryRepo->insertEditHistory('post', $target, $visitor, $this->originalTargetMessage, $ip);
			$target->edit_count++;

			if ($target->message != $preEditMergeMessage)
			{
				$editHistoryRepo->insertEditHistory('post', $target, $visitor, $preEditMergeMessage, $ip);
				$target->edit_count++;
			}
		}
	}

	protected function cleanupActions()
	{
		$target = $this->target;
		$targetThread = $this->target->Thread;
		$postIds = array_keys($this->sourcePosts);

		if ($postIds)
		{
			$this->app->jobManager()->enqueue(SearchIndex::class, [
				'content_type' => 'post',
				'content_ids' => $postIds,
			]);
		}

		if ($this->log)
		{
			$this->app->logger()->logModeratorAction(
				'post',
				$target,
				'merge_target',
				['ids' => implode(', ', $postIds)]
			);
		}

		/** @var ThreadRepository $threadRepo */
		$threadRepo = $this->repository(ThreadRepository::class);
		$threadRepo->rebuildThreadPostPositions($targetThread->thread_id);
		$threadRepo->rebuildThreadUserPostCounters($targetThread->thread_id);

		/** @var ReactionRepository $reactionRepo */
		$reactionRepo = $this->repository(ReactionRepository::class);
		$reactionRepo->rebuildContentReactionCache('post', $target->post_id);
	}
}
