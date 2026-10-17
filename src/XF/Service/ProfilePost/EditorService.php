<?php

namespace XF\Service\ProfilePost;

use XF\App;
use XF\Entity\ProfilePost;
use XF\Repository\ProfilePostRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var ProfilePost
	 */
	protected $profilePost;

	/**
	 * @var PreparerService
	 */
	protected $preparer;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ProfilePost $profilePost)
	{
		parent::__construct($app);
		$this->setProfilePost($profilePost);
	}

	protected function setProfilePost(ProfilePost $profilePost)
	{
		$this->profilePost = $profilePost;
		$this->preparer = $this->service(PreparerService::class, $profilePost);
	}

	public function getProfilePost()
	{
		return $this->profilePost;
	}

	public function getPreparer()
	{
		return $this->preparer;
	}

	public function setMessage($message, $format = true)
	{
		return $this->preparer->setMessage($message, $format);
	}

	public function setAttachmentHash($hash)
	{
		$this->preparer->setAttachmentHash($hash);
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function checkForSpam()
	{
		if ($this->profilePost->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->profilePost->preSave();
		return $this->profilePost->getErrors();
	}

	protected function _save()
	{
		$db = $this->db();
		$db->beginTransaction();

		$profilePost = $this->profilePost;
		$visitor = \XF::visitor();

		$profilePost->save(true, false);

		$this->preparer->afterUpdate();

		if ($profilePost->message_state == 'visible' && $this->alert && $profilePost->user_id != $visitor->user_id)
		{
			/** @var ProfilePostRepository $profilePostRepo */
			$profilePostRepo = $this->repository(ProfilePostRepository::class);
			$profilePostRepo->sendModeratorActionAlert($profilePost, 'edit', $this->alertReason);
		}

		$db->commit();

		return $profilePost;
	}
}
