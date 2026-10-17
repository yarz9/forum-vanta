<?php

namespace XF\Tfa;

use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Entity;

abstract class AbstractProvider
{
	protected $providerId;

	protected $bypassFailedAttemptLog = false;

	abstract public function generateInitialData(User $user, array $config = []);
	abstract public function trigger($context, User $user, array &$config, Request $request);
	abstract public function render($context, User $user, array $config, array $triggerData);
	abstract public function verify($context, User $user, array &$config, Request $request);

	public function __construct($providerId)
	{
		$this->providerId = $providerId;
	}

	public function isDeprecated(): bool
	{
		return false;
	}

	public function getBypassFailedAttemptLog()
	{
		return $this->bypassFailedAttemptLog;
	}

	public function getTitle()
	{
		return \XF::phrase('tfa.' . $this->providerId);
	}

	public function getDescription()
	{
		return \XF::phrase('tfa_desc.' . $this->providerId);
	}

	public function renderOptions(TfaProvider $provider)
	{
		return '';
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		return true;
	}

	public function verifyOptionsValue(array $options, &$error = null)
	{
		$optionsRequest = new Request(
			\XF::app()->inputFilterer(),
			$options
		);
		return $this->verifyOptions($optionsRequest, $options, $error);
	}

	public function isUsable()
	{
		return true;
	}

	public function canEnable()
	{
		return true;
	}

	public function meetsRequirements(User $user, &$error)
	{
		return true;
	}

	public function canDisable()
	{
		return true;
	}

	public function canManage()
	{
		return false;
	}

	public function handleManage(
		Controller $controller,
		TfaProvider $provider,
		User $user,
		array $config
	)
	{
		return null;
	}

	public function requiresConfig()
	{
		return false;
	}

	public function handleConfig(
		Controller $controller,
		TfaProvider $provider,
		User $user,
		array &$config
	)
	{
		return null;
	}

	public function getProviderId()
	{
		return $this->providerId;
	}

	/**
	 * @return null|Entity|TfaProvider
	 */
	public function getProvider()
	{
		return \XF::em()->find(TfaProvider::class, $this->providerId);
	}
}
