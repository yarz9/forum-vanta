<?php

namespace XF\Service\Post;

use XF\App;
use XF\Entity\Forum;
use XF\Entity\Post;
use XF\Entity\User;
use XF\Notifier\Post\ForumWatch;
use XF\Notifier\Post\Mention;
use XF\Notifier\Post\Quote;
use XF\Notifier\Post\ThreadWatch;
use XF\Service\AbstractNotifier;

class NotifierService extends AbstractNotifier
{
	/**
	 * @var Post
	 */
	protected $post;

	protected $actionType;

	public function __construct(App $app, Post $post, $actionType)
	{
		parent::__construct($app);

		switch ($actionType)
		{
			case 'reply':
			case 'thread':
				break;

			default:
				throw new \InvalidArgumentException("Unknown action type '$actionType'");
		}

		$this->actionType = $actionType;
		$this->post = $post;
	}

	public static function createForJob(array $extraData)
	{
		$post = \XF::app()->find(Post::class, $extraData['postId'], ['Thread', 'Thread.Forum']);
		if (!$post)
		{
			return null;
		}

		return \XF::service(NotifierService::class, $post, $extraData['actionType']);
	}

	protected function getExtraJobData()
	{
		return [
			'postId' => $this->post->post_id,
			'actionType' => $this->actionType,
		];
	}

	protected function loadNotifiers()
	{
		$notifiers = [
			'quote' => $this->app->notifier(Quote::class, $this->post),
			'mention' => $this->app->notifier(Mention::class, $this->post),
			'forumWatch' => $this->app->notifier(ForumWatch::class, $this->post, $this->actionType),
		];

		// if this is not the last post, then another notification would have been triggered already
		if ($this->post->isLastPost())
		{
			$notifiers['threadWatch'] = $this->app->notifier(ThreadWatch::class, $this->post, $this->actionType);
		}

		return $notifiers;
	}

	protected function loadExtraUserData(array $users)
	{
		$permCombinationIds = [];
		foreach ($users AS $user)
		{
			$id = $user->permission_combination_id;
			$permCombinationIds[$id] = $id;
		}

		$this->app->permissionCache()->cacheMultipleContentPermsForContent(
			$permCombinationIds,
			'node',
			$this->post->Thread->node_id
		);
	}

	protected function canUserViewContent(User $user)
	{
		return \XF::asVisitor(
			$user,
			function () { return $this->post->canView(); }
		);
	}

	public function skipUsersWatchingForum(Forum $forum)
	{
		$watchers = $this->db()->fetchAll("
			SELECT user_id, send_alert, send_email
			FROM xf_forum_watch
			WHERE node_id = ?
				AND (send_alert = 1 OR send_email = 1)
		", $forum->node_id);

		foreach ($watchers AS $watcher)
		{
			if ($watcher['send_alert'])
			{
				$this->setUserAsAlerted($watcher['user_id']);
			}
			if ($watcher['send_email'])
			{
				$this->setUserAsEmailed($watcher['user_id']);
			}
		}
	}
}
