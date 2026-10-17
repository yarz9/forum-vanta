<?php

namespace XF\Service\Thread;

use XF\App;
use XF\Entity\Thread;
use XF\Service\AbstractService;
use XF\Service\Post\NotifierService;

class ApproverService extends AbstractService
{
	/**
	 * @var Thread
	 */
	protected $thread;

	protected $notifyRunTime = 3;

	public function __construct(App $app, Thread $thread)
	{
		parent::__construct($app);
		$this->thread = $thread;
	}

	public function getThread()
	{
		return $this->thread;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->thread->discussion_state == 'moderated')
		{
			$this->thread->discussion_state = 'visible';
			$this->thread->save();

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
		$post = $this->thread->FirstPost;

		if ($post)
		{
			/** @var NotifierService $notifier */
			$notifier = $this->service(NotifierService::class, $post, 'thread');
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}
