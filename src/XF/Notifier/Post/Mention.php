<?php

namespace XF\Notifier\Post;

use XF\App;
use XF\Entity\Post;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;

class Mention extends AbstractNotifier
{
	/**
	 * @var Post
	 */
	protected $post;

	public function __construct(App $app, Post $post)
	{
		parent::__construct($app);

		$this->post = $post;
	}

	public function canNotify(User $user)
	{
		return ($user->user_id != $this->post->user_id);
	}

	public function sendAlert(User $user)
	{
		$post = $this->post;
		return $this->basicAlert(
			$user,
			$post->user_id,
			$post->username,
			'post',
			$post->post_id,
			'mention'
		);
	}
}
