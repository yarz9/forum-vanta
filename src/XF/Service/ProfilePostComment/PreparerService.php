<?php

namespace XF\Service\ProfilePostComment;

use XF\App;
use XF\Entity\ProfilePostComment;
use XF\Entity\User;
use XF\Repository\IpRepository;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;

class PreparerService extends AbstractService
{
	/**
	 * @var ProfilePostComment
	 */
	protected $comment;

	protected $attachmentHash;

	protected $logIp = true;

	protected $mentionedUsers = [];

	public function __construct(App $app, ProfilePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	protected function setComment(ProfilePostComment $comment)
	{
		$this->comment = $comment;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
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

	public function setMessage($message, $format = true)
	{
		$preparer = $this->getMessagePreparer($format);
		$preparer->setConstraint('maxLength', $this->app->options()->profilePostMaxLength);
		$this->comment->message = $preparer->prepare($message);
		$this->comment->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->comment);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\PreparerService
	 */
	protected function getMessagePreparer($format = true)
	{
		/** @var \XF\Service\Message\PreparerService $preparer */
		$preparer = $this->service(\XF\Service\Message\PreparerService::class, 'profile_post_comment', $this->comment);
		$preparer->enableFilter('structuredText');
		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}

	public function setAttachmentHash($hash)
	{
		$this->attachmentHash = $hash;
	}

	public function checkForSpam()
	{
		$comment = $this->comment;

		/** @var User $user */
		$user = $comment->User ?: $this->repository(UserRepository::class)->getGuestUser($comment->username);
		$message = $comment->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'profile_post_comment',
			'content_id' => $comment->profile_post_comment_id,
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$comment->message_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('profile_post_comment', null);
				$comment->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}

	public function afterInsert()
	{
		if ($this->attachmentHash)
		{
			$this->associateAttachments($this->attachmentHash);
		}

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('profile_post_comment', $this->comment->profile_post_comment_id);
	}

	public function afterUpdate()
	{
		if ($this->attachmentHash)
		{
			$this->associateAttachments($this->attachmentHash);
		}

		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('profile_post_comment', $this->comment->profile_post_comment_id);
	}

	protected function associateAttachments($hash)
	{
		$comment = $this->comment;

		/** @var \XF\Service\Attachment\PreparerService $inserter */
		$inserter = $this->service(\XF\Service\Attachment\PreparerService::class);
		$associated = $inserter->associateAttachmentsWithContent($hash, 'profile_post_comment', $comment->profile_post_comment_id);
		if ($associated)
		{
			$comment->fastUpdate('attach_count', $comment->attach_count + $associated);
		}
	}

	protected function writeIpLog($ip)
	{
		$comment = $this->comment;
		if (!$comment->user_id)
		{
			return;
		}

		/** @var IpRepository $ipRepo */
		$ipRepo = $this->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp($comment->user_id, $ip, 'profile_post_comment', $comment->profile_post_comment_id);
		if ($ipEnt)
		{
			$comment->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}
