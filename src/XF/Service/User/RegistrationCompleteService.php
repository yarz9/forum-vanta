<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Repository\PreRegActionRepository;
use XF\Service\AbstractService;

class RegistrationCompleteService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var Entity|null
	 */
	protected $preRegContent;

	public function __construct(App $app, User $user)
	{
		parent::__construct($app);

		$this->user = $user;
	}

	public function triggerCompletionActions()
	{
		/** @var WelcomeService $userWelcome */
		$userWelcome = $this->service(WelcomeService::class, $this->user);
		$userWelcome->send();

		$this->repository(PreRegActionRepository::class)->completeUserAction($this->user, $preRegContent);
		$this->preRegContent = $preRegContent;
	}

	/**
	 * @return Entity|null
	 */
	public function getPreRegContent()
	{
		return $this->preRegContent;
	}
}
