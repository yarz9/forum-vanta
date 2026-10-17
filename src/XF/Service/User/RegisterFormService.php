<?php

namespace XF\Service\User;

use XF\App;
use XF\Http\Request;
use XF\Service\AbstractService;
use XF\Session\Session;

class RegisterFormService extends AbstractService
{
	protected $hashedFields = [
		'username',
		'email',
		'password',
		'timezone',
	];

	protected $honeyPotFields = [
		'username_hp',
		'email_hp',
		'password_hp',
	];

	protected $secretKey;
	protected $uniqueKey;
	protected $startTime;

	public function __construct(App $app, ?Session $session = null)
	{
		parent::__construct($app);

		if ($session)
		{
			$this->setupFromSession($session);
		}
		else
		{
			$this->generateState();
		}
	}

	public function generateState()
	{
		$this->secretKey = \XF::generateRandomString(16);
		$this->uniqueKey = \XF::generateRandomString(16);
		$this->startTime = \XF::$time;
	}

	public function setupFromSession(Session $session)
	{
		$values = $session->get('registration');
		if ($values)
		{
			$this->secretKey = $values['secret'];
			$this->uniqueKey = $values['unique'];
			$this->startTime = $values['time'];
		}
		else
		{
			$this->generateState();
		}
	}

	public function saveStateToSession(Session $session)
	{
		$session->set('registration', [
			'secret' => $this->secretKey,
			'unique' => $this->uniqueKey,
			'time' => $this->startTime,
		]);
	}

	public function clearStateFromSession(Session $session)
	{
		$session->remove('registration');
	}

	public function getSecretKey()
	{
		return $this->secretKey;
	}

	public function getUniqueKey()
	{
		return $this->uniqueKey;
	}

	public function getStartTime()
	{
		return $this->startTime;
	}

	public function getFieldName($field)
	{
		return hash_hmac('sha1', $field, $this->secretKey);
	}

	public function isValidRegistrationAttempt(Request $request, &$error = null)
	{
		$options = $this->app->options();

		if (!$this->startTime || ($this->startTime + $options->registrationTimer) > time())
		{
			$error = \XF::phrase('sorry_you_must_wait_longer_to_create_account');
			return false;
		}

		if (!$this->uniqueKey || $this->uniqueKey !== $request->filter('reg_key', 'str'))
		{
			$error = \XF::phrase('something_went_wrong_please_try_again');
			return false;
		}

		foreach ($this->hashedFields AS $field)
		{
			$value = $request->filter($field, 'str');
			if ($value !== '')
			{
				$error = \XF::phrase('some_fields_contained_unexpected_data_try_again');
				return false;
			}

			if (strpos($field, 'password') !== false)
			{
				$request->skipKeyForLogging($this->getFieldName($field));
			}
		}

		foreach ($this->honeyPotFields AS $field)
		{
			$value = $request->filter($this->getFieldName($field), 'str');
			if ($value !== '')
			{
				$error = \XF::phrase('some_fields_contained_unexpected_data_try_again');
				return false;
			}

			if (strpos($field, 'password') !== false)
			{
				$request->skipKeyForLogging($this->getFieldName($field));
			}
		}

		return true;
	}

	public function getHashedInputValues(Request $request)
	{
		$values = [];
		foreach ($this->hashedFields AS $field)
		{
			$values[$field] = $request->filter($this->getFieldName($field), 'str');
		}

		return $values;
	}

	public function getUnhashedInputValues(Request $request)
	{
		$values = [];
		foreach ($this->hashedFields AS $field)
		{
			$values[$field] = $request->filter($field, 'str');
		}

		return $values;
	}
}
