<?php

namespace XF\Service\ProfilePostComment;

use XF\App;
use XF\Entity\ProfilePostComment;
use XF\Entity\User;
use XF\Repository\ProfilePostRepository;
use XF\Service\AbstractService;

class DeleterService extends AbstractService
{
	/**
	 * @var ProfilePostComment
	 */
	protected $comment;

	/**
	 * @var User
	 */
	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ProfilePostComment $content)
	{
		parent::__construct($app);
		$this->setComment($content);
		$this->setUser(\XF::visitor());
	}

	protected function setComment(ProfilePostComment $content)
	{
		$this->comment = $content;
	}

	public function getComment()
	{
		return $this->comment;
	}

	protected function setUser(User $user)
	{
		$this->user = $user;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user;

		$comment = $this->comment;
		$wasVisible = ($comment->message_state == 'visible');

		if ($type == 'soft')
		{
			$result = $comment->softDelete($reason, $user);
		}
		else
		{
			$result = $comment->delete();
		}

		if ($result && $wasVisible && $this->alert && $comment->user_id != $user->user_id)
		{
			/** @var ProfilePostRepository $profilePostRepo */
			$profilePostRepo = $this->repository(ProfilePostRepository::class);
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'delete', $this->alertReason);
		}

		return $result;
	}
}
