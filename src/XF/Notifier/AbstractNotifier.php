<?php

namespace XF\Notifier;

use XF\App;
use XF\Entity\User;
use XF\Repository\UserAlertRepository;

abstract class AbstractNotifier
{
	/**
	 * @var App
	 */
	protected $app;

	public function __construct(App $app)
	{
		$this->app = $app;
	}

	public function canNotify(User $user)
	{
		return true;
	}

	public function sendAlert(User $user)
	{
		return false;
	}

	public function sendEmail(User $user)
	{
		return false;
	}

	public function getDefaultNotifyData()
	{
		return [];
	}

	public function getUserData(array $userIds)
	{
		$users = \XF::em()->findByIds(User::class, $userIds, $this->getUserWith());
		return $users->toArray();
	}

	protected function getUserWith()
	{
		// these will generally be used for alerts, ignore
		return ['Profile', 'Option'];
	}

	protected function basicAlert(
		User $receiver,
		$senderId,
		$senderName,
		$contentType,
		$contentId,
		$action,
		array $extra = [],
		array $options = []
	)
	{
		// generic alerts default to autoRead=true, but notification alerts normally relate to specific content
		// so we can default them to false
		if (!isset($options['autoRead']))
		{
			$options['autoRead'] = false;
		}

		$alertRepo = $this->app()->repository(UserAlertRepository::class);
		return $alertRepo->alert(
			$receiver,
			$senderId,
			$senderName,
			$contentType,
			$contentId,
			$action,
			$extra,
			$options
		);
	}

	protected function app()
	{
		return \XF::app();
	}
}
