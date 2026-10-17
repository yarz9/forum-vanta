<?php

namespace XF\Service\ProfilePostComment;

use XF\App;
use XF\Entity\ProfilePost;
use XF\Entity\ProfilePostComment;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var ProfilePost
	 */
	protected $profilePost;

	/**
	 * @var ProfilePostComment
	 */
	protected $comment;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var PreparerService
	 */
	protected $preparer;

	public function __construct(App $app, ProfilePost $profilePost)
	{
		parent::__construct($app);
		$this->setProfilePost($profilePost);
		$this->setUser(\XF::visitor());
		$this->setDefaults();
	}

	protected function setProfilePost(ProfilePost $profilePost)
	{
		$this->profilePost = $profilePost;
		$this->comment = $profilePost->getNewComment();
		$this->preparer = $this->service(PreparerService::class, $this->comment);
	}

	public function getProfilePost()
	{
		return $this->profilePost;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function getProfilePostCommentPreparer()
	{
		return $this->preparer;
	}

	public function logIp($logIp)
	{
		$this->preparer->logIp($logIp);
	}

	protected function setUser(User $user)
	{
		$this->user = $user;
	}

	protected function setDefaults()
	{
		$this->comment->message_state = $this->profilePost->getNewContentState();
		$this->comment->user_id = $this->user->user_id;
		$this->comment->username = $this->user->username;
	}

	public function setContent($message, $format = true)
	{
		return $this->preparer->setMessage($message, $format);
	}

	public function setAttachmentHash($hash)
	{
		$this->preparer->setAttachmentHash($hash);
	}

	public function checkForSpam()
	{
		if ($this->comment->message_state == 'visible' && $this->user->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$this->comment->comment_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->comment->preSave();
		return $this->comment->getErrors();
	}

	protected function _save()
	{
		$comment = $this->comment;
		$comment->save();

		$this->preparer->afterInsert();

		return $comment;
	}

	public function sendNotifications()
	{
		if ($this->comment->isVisible())
		{
			/** @var NotifierService $notifier */
			$notifier = $this->service(NotifierService::class, $this->comment);
			$notifier->setNotifyMentioned($this->preparer->getMentionedUserIds());
			$notifier->notify();
		}
	}
}
