<?php

namespace XF\Service\User;

use XF\App;
use XF\Db\DuplicateKeyException;
use XF\Entity\User;
use XF\Entity\UserFollow;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

class FollowService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $followedBy;

	/**
	 * @var User
	 */
	protected $followUser;

	protected $silent = false;

	public function __construct(App $app, User $followUser, ?User $followedBy = null)
	{
		parent::__construct($app);

		$this->followUser = $followUser;
		$this->followedBy = $followedBy ?: \XF::visitor();
	}

	public function setSilent($silent)
	{
		$this->silent = (bool) $silent;
	}

	public function follow()
	{
		$userFollow = $this->em()->create(UserFollow::class);
		$userFollow->user_id = $this->followedBy->user_id;
		$userFollow->follow_user_id = $this->followUser->user_id;

		try
		{
			$saved = $userFollow->save(false);
		}
		catch (DuplicateKeyException $e)
		{
			$saved = false;

			$dupe = $this->em()->findOne(UserFollow::class, [
				'user_id' => $this->followedBy->user_id,
				'follow_user_id' => $this->followUser->user_id,
			]);
			if ($dupe)
			{
				$userFollow = $dupe;
			}
		}

		if ($saved)
		{
			$this->sendFollowingAlert();
		}

		return $userFollow;
	}

	protected function sendFollowingAlert()
	{
		if ($this->silent)
		{
			return;
		}

		$followedBy = $this->followedBy;
		$followUser = $this->followUser;

		if (!$followUser->isIgnoring($followedBy->user_id)
			&& $followUser->Option->doesReceiveAlert('user', 'following')
		)
		{
			/** @var UserAlertRepository $alertRepo */
			$alertRepo = $this->repository(UserAlertRepository::class);
			$alertRepo->alert(
				$followUser,
				$followedBy->user_id,
				$followedBy->username,
				'user',
				$followUser->user_id,
				'following',
				[]
			);
		}
	}

	public function unfollow()
	{
		$userFollow = $this->em()->findOne(UserFollow::class, [
			'user_id' => $this->followedBy->user_id,
			'follow_user_id' => $this->followUser->user_id,
		]);

		if ($userFollow && $userFollow->delete())
		{
			$this->deleteFollowingAlert();
		}

		return $userFollow;
	}

	protected function deleteFollowingAlert()
	{
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertRepo->fastDeleteAlertsFromUser(
			$this->followedBy->user_id,
			'user',
			$this->followUser->user_id,
			'following'
		);
	}
}
