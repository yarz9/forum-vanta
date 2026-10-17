<?php

namespace XF\Service\Report;

use XF\App;
use XF\Entity\ReportComment;
use XF\Entity\User;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;
use XF\Service\Message\PreparerService;

class CommentPreparerService extends AbstractService
{
	/**
	 * @var ReportComment
	 */
	protected $comment;

	protected $mentionedUsers = [];

	public function __construct(App $app, ReportComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	public function setComment(ReportComment $comment)
	{
		$this->comment = $comment;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function setUser(User $user)
	{
		$this->comment->user_id = $user->user_id;
		$this->comment->username = $user->username;
	}

	public function setMessage($message, $format = true)
	{
		$preparer = $this->getMessagePreparer($format);
		$this->comment->message = $preparer->prepare($message);

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->comment);
	}

	public function getMentionedUsers($limitPermissions = true)
	{
		if ($limitPermissions && $this->comment)
		{
			/** @var User $user */
			$user = $this->comment->User ?: $this->repository(UserRepository::class)->getGuestUser();
			return $user->getAllowedUserMentions($this->mentionedUsers);
		}
		else
		{
			return $this->mentionedUsers;
		}
	}

	public function getMentionedUserIds($limitPermissions = true)
	{
		return array_keys($this->getMentionedUsers($limitPermissions));
	}

	/**
	 * @param bool $format
	 *
	 * @return PreparerService
	 */
	protected function getMessagePreparer($format = true)
	{
		/** @var PreparerService $preparer */
		$preparer = $this->service(PreparerService::class, 'report_comment', $this->comment);
		if (!$format)
		{
			$preparer->disableAllFilters();
		}
		$preparer->setConstraint('allowEmpty', true);

		return $preparer;
	}
}
