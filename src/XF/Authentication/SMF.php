<?php

namespace XF\Authentication;

use XF\Util\Str;

use function is_string;

class SMF extends AbstractAuth
{
	protected function createHash($password, $username)
	{
		return sha1($username . Str::from_html($password));
	}

	public function generate($password)
	{
		throw new \LogicException('Cannot generate authentication for this type.');
	}

	public function authenticate($userId, $password)
	{
		if (!is_string($password) || $password === '' || empty($this->data))
		{
			return false;
		}

		// note: importer already stores username strtolower
		if (password_verify($this->data['username'] . $password, $this->data['hash']))
		{
			// SMF 2.1
			return true;
		}

		// pre-SMF 2.1
		$userHash = $this->createHash($password, $this->data['username']);
		return hash_equals($this->data['hash'], $userHash);
	}

	public function getAuthenticationName()
	{
		return 'XF:SMF';
	}
}
