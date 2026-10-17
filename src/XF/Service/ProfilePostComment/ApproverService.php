<?php

namespace XF\Service\ProfilePostComment;

use XF\App;
use XF\Entity\ProfilePostComment;
use XF\Service\AbstractService;

class ApproverService extends AbstractService
{
	/**
	 * @var ProfilePostComment
	 */
	protected $comment;

	protected $notifyRunTime = 3;

	public function __construct(App $app, ProfilePostComment $comment)
	{
		parent::__construct($app);
		$this->comment = $comment;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->comment->message_state == 'moderated')
		{
			$this->comment->message_state = 'visible';
			$this->comment->save();

			$this->onApprove();
			return true;
		}
		else
		{
			return false;
		}
	}

	protected function onApprove()
	{
		if ($this->comment->isLastComment())
		{
			/** @var NotifierService $notifier */
			$notifier = $this->service(NotifierService::class, $this->comment);
			$notifier->notify();
		}
	}
}
