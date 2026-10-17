<?php

namespace XF\Service\Post;

use XF\App;
use XF\Entity\Post;
use XF\Service\AbstractService;

class ApproverService extends AbstractService
{
	/**
	 * @var Post
	 */
	protected $post;

	protected $notifyRunTime = 3;

	public function __construct(App $app, Post $post)
	{
		parent::__construct($app);
		$this->post = $post;
	}

	public function getPost()
	{
		return $this->post;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->post->message_state == 'moderated')
		{
			$this->post->message_state = 'visible';
			$this->post->save();

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
		/** @var PreparerService $preparer */
		$preparer = $this->service(PreparerService::class, $this->post);
		$preparer->setMessage($this->post->message);

		// TODO: this doesn't solve mentioned user IDs
		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $this->post, 'reply');
		$notifier->setQuotedUserIds($preparer->getQuotedUserIds());
		$notifier->notifyAndEnqueue($this->notifyRunTime);
	}
}
