<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Http\Request;
use XF\Repository\TfaAttemptRepository;
use XF\Repository\TfaRepository;
use XF\Service\AbstractService;
use XF\Tfa\AbstractProvider;

class TfaService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var TfaRepository
	 */
	protected $tfaRepo;

	/**
	 * @var TfaProvider[]
	 */
	protected $providers;

	protected $recordAttempts = true;

	public function __construct(App $app, User $user)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->tfaRepo = $this->repository(TfaRepository::class);
		$this->providers = $this->tfaRepo->getAvailableProvidersForUser($user->user_id);
	}

	public function isTfaAvailable()
	{
		return $this->providers ? true : false;
	}

	public function isProviderValid($providerId)
	{
		return $providerId && isset($this->providers[$providerId]);
	}

	public function setRecordAttempts($value)
	{
		$this->recordAttempts = (bool) $value;
	}

	public function getRecordAttempts()
	{
		return $this->recordAttempts;
	}

	/**
	 * @return TfaProvider[]
	 */
	public function getProviders()
	{
		return $this->providers;
	}

	public function hasTooManyTfaAttempts()
	{
		$limits = $this->getAttemptLimits();
		$userId = $this->user->user_id;

		/** @var TfaAttemptRepository $attemptRepo */
		$attemptRepo = $this->repository(TfaAttemptRepository::class);

		foreach ($limits AS $limit)
		{
			$cutOff = \XF::$time - $limit['time'];
			$count = $limit['count'];

			if ($attemptRepo->countTfaAttemptsSince($cutOff, $userId) >= $count)
			{
				return true;
			}
		}

		return false;
	}

	public function getAttemptLimits()
	{
		return [
			['time' => 60, 'count' => 4],
			['time' => 60 * 5, 'count' => 8],
		];
	}

	public function trigger(Request $request, $providerId = null)
	{
		if ($providerId && isset($this->providers[$providerId]))
		{
			$provider = $this->providers[$providerId];
		}
		else
		{
			$provider = reset($this->providers);
		}

		$providerData = $provider->getUserProviderConfig($this->user->user_id);

		/** @var AbstractProvider $handler */
		$handler = $provider->handler;
		$triggerData = $handler->trigger('login', $this->user, $providerData, $request);

		/** @var TfaRepository $tfaRepo */
		$tfaRepo = $this->repository(TfaRepository::class);
		$tfaRepo->updateUserTfaData($this->user, $provider, $providerData, false);

		return [
			'provider' => $provider,
			'providerData' => $providerData,
			'triggerData' => $triggerData,
		];
	}

	public function verify(Request $request, $providerId)
	{
		$provider = $this->providers[$providerId];
		$providerData = $provider->getUserProviderConfig($this->user->user_id);

		/** @var AbstractProvider $handler */
		$handler = $provider->handler;

		if (!$handler->verify('login', $this->user, $providerData, $request))
		{
			$bypassLogging = $handler->getBypassFailedAttemptLog();

			if (!$bypassLogging)
			{
				$this->recordFailedAttempt();
			}

			return false;
		}

		$this->tfaRepo->updateUserTfaData($this->user, $provider, $providerData, true);
		$this->clearFailedAttempts();

		return true;
	}

	protected function recordFailedAttempt()
	{
		if (!$this->recordAttempts)
		{
			return;
		}

		/** @var TfaAttemptRepository $attemptRepo */
		$attemptRepo = $this->repository(TfaAttemptRepository::class);
		$attemptRepo->logFailedTfaAttempt($this->user->user_id);
	}

	protected function clearFailedAttempts()
	{
		if (!$this->recordAttempts)
		{
			return;
		}

		/** @var TfaAttemptRepository $attemptRepo */
		$attemptRepo = $this->repository(TfaAttemptRepository::class);
		$attemptRepo->clearTfaAttempts($this->user->user_id);
	}
}
