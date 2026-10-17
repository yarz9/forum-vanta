<?php

namespace XF\Service\ProfilePost;

use XF\App;
use XF\Entity\ProfilePost;
use XF\Entity\User;
use XF\Repository\IpRepository;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;

class PreparerService extends AbstractService
{
	/**
	 * @var ProfilePost
	 */
	protected $profilePost;

	protected $attachmentHash;

	protected $logIp = true;

	protected $mentionedUsers = [];

	public function __construct(App $app, ProfilePost $profilePost)
	{
		parent::__construct($app);
		$this->setProfilePost($profilePost);
	}

	protected function setProfilePost(ProfilePost $profilePost)
	{
		$this->profilePost = $profilePost;
	}

	public function getProfilePost()
	{
		return $this->profilePost;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getMentionedUsers($limitPermissions = true)
	{
		if ($limitPermissions && $this->profilePost)
		{
			/** @var User $user */
			$user = $this->profilePost->User ?: $this->repository(UserRepository::class)->getGuestUser();
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
		$this->profilePost->message = $preparer->prepare($message);
		$this->profilePost->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->profilePost);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\PreparerService
	 */
	protected function getMessagePreparer($format = true)
	{
		/** @var \XF\Service\Message\PreparerService $preparer */
		$preparer = $this->service(\XF\Service\Message\PreparerService::class, 'profile_post', $this->profilePost);
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
		$profilePost = $this->profilePost;

		/** @var User $user */
		$user = $profilePost->User ?: $this->repository(UserRepository::class)->getGuestUser($profilePost->username);
		$message = $profilePost->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'profile_post',
			'content_id' => $profilePost->profile_post_id,
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$profilePost->message_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('profile_post', null);
				$profilePost->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
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
		$checker->logSpamTrigger('profile_post', $this->profilePost->profile_post_id);
	}

	public function afterUpdate()
	{
		if ($this->attachmentHash)
		{
			$this->associateAttachments($this->attachmentHash);
		}

		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('profile_post', $this->profilePost->profile_post_id);
	}

	protected function associateAttachments($hash)
	{
		$profilePost = $this->profilePost;

		/** @var \XF\Service\Attachment\PreparerService $inserter */
		$inserter = $this->service(\XF\Service\Attachment\PreparerService::class);
		$associated = $inserter->associateAttachmentsWithContent($hash, 'profile_post', $profilePost->profile_post_id);
		if ($associated)
		{
			$profilePost->fastUpdate('attach_count', $profilePost->attach_count + $associated);
		}
	}

	protected function writeIpLog($ip)
	{
		$profilePost = $this->profilePost;
		if (!$profilePost->user_id)
		{
			return;
		}

		/** @var IpRepository $ipRepo */
		$ipRepo = $this->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp($profilePost->user_id, $ip, 'profile_post', $profilePost->profile_post_id);
		if ($ipEnt)
		{
			$profilePost->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}
