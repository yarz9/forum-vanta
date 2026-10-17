<?php

namespace XF\Service\ProfilePostComment;

use XF\App;
use XF\Entity\ProfilePostComment;
use XF\Repository\ProfilePostRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var ProfilePostComment
	 */
	protected $comment;

	/**
	 * @var PreparerService
	 */
	protected $preparer;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ProfilePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	protected function setComment(ProfilePostComment $comment)
	{
		$this->comment = $comment;
		$this->preparer = $this->service(PreparerService::class, $this->comment);
	}

	public function getComment()
	{
		return $this->comment;
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
		if ($this->comment->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
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

		$this->comment->preSave();
		return $this->comment->getErrors();
	}

	protected function _save()
	{
		$db = $this->db();
		$db->beginTransaction();

		$comment = $this->comment;
		$visitor = \XF::visitor();

		$comment->save(true, false);

		$this->preparer->afterUpdate();

		if ($comment->message_state == 'visible' && $this->alert && $comment->user_id != $visitor->user_id)
		{
			/** @var ProfilePostRepository $profilePostRepo */
			$profilePostRepo = $this->repository(ProfilePostRepository::class);
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'edit', $this->alertReason);
		}

		$db->commit();

		return $comment;
	}
}
