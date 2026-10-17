<?php

namespace XF\Service\User;

use XF\App;
use XF\Db\DuplicateKeyException;
use XF\Entity\User;
use XF\Entity\UserIgnored;
use XF\Service\AbstractService;

class IgnoreService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $ignoredBy;

	/**
	 * @var User
	 */
	protected $ignoredUser;

	public function __construct(App $app, User $ignoredUser, ?User $ignoredBy = null)
	{
		parent::__construct($app);

		$this->ignoredUser = $ignoredUser;
		$this->ignoredBy = $ignoredBy ?: \XF::visitor();
	}

	public function ignore()
	{
		$userIgnored = $this->em()->create(UserIgnored::class);
		$userIgnored->user_id = $this->ignoredBy->user_id;
		$userIgnored->ignored_user_id = $this->ignoredUser->user_id;

		try
		{
			$userIgnored->save(false);
		}
		catch (DuplicateKeyException $e)
		{
			$dupe = $this->em()->findOne(UserIgnored::class, [
				'user_id' => $this->ignoredBy->user_id,
				'ignored_user_id' => $this->ignoredUser->user_id,
			]);
			if ($dupe)
			{
				$userIgnored = $dupe;
			}
		}

		return $userIgnored;
	}

	public function unignore()
	{
		$userIgnored = $this->em()->findOne(UserIgnored::class, [
			'user_id' => $this->ignoredBy->user_id,
			'ignored_user_id' => $this->ignoredUser->user_id,
		]);

		if ($userIgnored)
		{
			$userIgnored->delete(false);
		}

		return $userIgnored;
	}
}
