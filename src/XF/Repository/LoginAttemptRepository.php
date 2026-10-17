<?php

namespace XF\Repository;

use XF\Entity\LoginAttempt;
use XF\Finder\LoginAttemptFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Util\Ip;
use XF\Util\Str;

class LoginAttemptRepository extends Repository
{
	public function logFailedLogin($login, $ip)
	{
		$loginAttempt = $this->em->create(LoginAttempt::class);
		$loginAttempt->bulkSet([
			'login' => Str::substr($login, 0, 60),
			'ip_address' => Ip::stringToBinary($ip),
			'attempt_date' => time(),
		]);
		$loginAttempt->save();
	}

	public function countLoginAttemptsSince($cutOff, $ip, $login = null)
	{
		$ipAddress = Ip::stringToBinary($ip);

		$db = $this->db();
		$loginWhere = ($login ? "AND login = " . $db->quote($login) : '');

		return $db->fetchOne("
			SELECT COUNT(*)
			FROM xf_login_attempt
			WHERE attempt_date >= ?
				AND ip_address = ?
				{$loginWhere}
		", [$cutOff, $ipAddress]);
	}

	public function clearLoginAttempts($login, $ip)
	{
		/** @var Finder $finder */
		$finder = $this->finder(LoginAttemptFinder::class);

		$attempts = $finder->where('login', $login)
			->where('ip_address', Ip::stringToBinary($ip))
			->fetch();

		foreach ($attempts AS $attempt)
		{
			$attempt->delete();
		}
	}

	public function cleanUpLoginAttempts()
	{
		$this->db()->delete('xf_login_attempt', 'attempt_date < ?', time() - 86400);
	}
}
