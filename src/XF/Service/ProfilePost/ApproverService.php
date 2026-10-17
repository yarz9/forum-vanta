<?php

namespace XF\Service\ProfilePost;

use XF\App;
use XF\Entity\ProfilePost;
use XF\Service\AbstractService;

class ApproverService extends AbstractService
{
	/**
	 * @var ProfilePost
	 */
	protected $profilePost;

	public function __construct(App $app, ProfilePost $profilePost)
	{
		parent::__construct($app);
		$this->profilePost = $profilePost;
	}

	public function getProfilePost()
	{
		return $this->profilePost;
	}

	public function approve()
	{
		if ($this->profilePost->message_state == 'moderated')
		{
			$this->profilePost->message_state = 'visible';
			$this->profilePost->save();

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
		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $this->profilePost);
		$notifier->notify();
	}
}
