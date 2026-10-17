<?php

namespace XF\Repository;

use XF\Db\DuplicateKeyException;
use XF\Entity\BanEmail;
use XF\Entity\IpMatch;
use XF\Entity\User;
use XF\Entity\UserBan;
use XF\Finder\BanEmailFinder;
use XF\Finder\IpMatchFinder;
use XF\Finder\UserBanFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Util\Ip;

class BanningRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findUserBansForList()
	{
		return $this->finder(UserBanFinder::class)
			->setDefaultOrder([['ban_date', 'DESC'], ['User.username']]);
	}

	public function banUser(User $user, $endDate, $reason, &$error = null, ?User $banBy = null)
	{
		if ($endDate < time() && $endDate !== 0) // 0 === permanent
		{
			$error = \XF::phraseDeferred('please_enter_a_date_in_the_future');
			return false;
		}

		$banBy = $banBy ?: \XF::visitor();

		/** @var UserBan $userBan */
		$userBan = $user->getRelationOrDefault('Ban', false);
		if ($userBan->isInsert())
		{
			$userBan->ban_user_id = $banBy->user_id;
		}

		$userBan->end_date = $endDate;
		if ($userBan->isChanged('end_date'))
		{
			$userBan->triggered = false;
		}
		$userBan->user_reason = $reason;

		if (!$userBan->preSave())
		{
			$errors = $userBan->getErrors();
			$error = reset($errors);
			return false;
		}

		try
		{
			$userBan->save(false);
		}
		catch (DuplicateKeyException $e)
		{
			// likely a race condition, keep the old value and accept
		}

		return true;
	}

	public function deleteExpiredUserBans($cutOff = null)
	{
		foreach ($this->findExpiredUserBans($cutOff)->fetch() AS $userBan)
		{
			$userBan->delete();
		}
	}

	public function findExpiredUserBans($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = time();
		}

		return $this->finder(UserBanFinder::class)
			->where('end_date', '>', 0)
			->where('end_date', '<=', $cutOff);
	}

	/**
	 * @return Finder
	 */
	public function findEmailBans()
	{
		return $this->finder(BanEmailFinder::class)
			->setDefaultOrder('banned_email', 'asc');
	}

	public function banEmail($email, $reason = '', ?User $user = null)
	{
		$user = $user ?: \XF::visitor();

		$emailBan = $this->em->create(BanEmail::class);
		$emailBan->banned_email = $email;
		$emailBan->reason = $reason;
		$emailBan->create_user_id = $user->user_id;

		return $emailBan->save();
	}

	public function isEmailBanned($email, array $bannedEmails)
	{
		foreach ($bannedEmails AS $bannedEmail)
		{
			$bannedEmailTest = str_replace('\\*', '(.*)', preg_quote($bannedEmail, '/'));
			if (preg_match('/^' . $bannedEmailTest . '$/i', $email))
			{
				return true;
			}
		}

		return false;
	}

	public function getBannedEntryFromEmail($email, array $bannedEmails)
	{
		foreach ($bannedEmails AS $bannedEmail)
		{
			$bannedEmailTest = str_replace('\\*', '(.*)', preg_quote($bannedEmail, '/'));
			if (preg_match('/^' . $bannedEmailTest . '$/i', $email))
			{
				return $bannedEmail;
			}
		}

		return null;
	}

	public function rebuildBannedEmailCache()
	{
		$cache = $this->findEmailBans()->fetch();
		$cache = $cache->pluckNamed('banned_email');

		\XF::registry()->set('bannedEmails', $cache);
		return $cache;
	}

	/**
	 * @return Finder
	 */
	public function findIpMatchesByRange($start, $end)
	{
		return $this->finder(IpMatchFinder::class)
			->where('start_range', $start)
			->where('end_range', $end);
	}

	/**
	 * @return Finder
	 */
	public function findIpBans()
	{
		return $this->finder(IpMatchFinder::class)
			->where('match_type', 'banned')
			->setDefaultOrder('start_range', 'asc');
	}

	public function banIp($ip, $reason = '', ?User $user = null)
	{
		$user = $user ?: \XF::visitor();

		[$niceIp, $firstByte, $startRange, $endRange] = $this->getIpRecord($ip);

		$ipBan = $this->em->create(IpMatch::class);
		$ipBan->ip = $niceIp;
		$ipBan->match_type = 'banned';
		$ipBan->first_byte = $firstByte;
		$ipBan->start_range = $startRange;
		$ipBan->end_range = $endRange;
		$ipBan->reason = $reason;
		$ipBan->create_user_id = $user->user_id;

		return $ipBan->save();
	}

	public function getBannedIpCacheData()
	{
		$data = [];
		foreach ($this->findIpBans()->fetch() AS $ipBan)
		{
			$data[$ipBan->first_byte][] = [$ipBan->start_range, $ipBan->end_range];
		}

		return [
			'version' => time(),
			'data' => $data,
		];
	}

	public function rebuildBannedIpCache()
	{
		$cache = $this->getBannedIpCacheData();
		\XF::registry()->set('bannedIps', $cache);
		return $cache;
	}

	/**
	 * @return Finder
	 */
	public function findDiscouragedIps()
	{
		return $this->finder(IpMatchFinder::class)
			->where('match_type', 'discouraged')
			->setDefaultOrder('start_range', 'asc');
	}

	public function discourageIp($ip, $reason = '', ?User $user = null)
	{
		$user = $user ?: \XF::visitor();

		[$niceIp, $firstByte, $startRange, $endRange] = $this->getIpRecord($ip);

		$discouragedIp = $this->em->create(IpMatch::class);
		$discouragedIp->ip = $niceIp;
		$discouragedIp->match_type = 'discouraged';
		$discouragedIp->first_byte = $firstByte;
		$discouragedIp->start_range = $startRange;
		$discouragedIp->end_range = $endRange;
		$discouragedIp->reason = $reason;
		$discouragedIp->create_user_id = $user->user_id;

		return $discouragedIp->save();
	}

	public function getDiscouragedIpCacheData()
	{
		$data = [];
		foreach ($this->findDiscouragedIps()->fetch() AS $discouragedIp)
		{
			$data[$discouragedIp->first_byte][] = [$discouragedIp->start_range, $discouragedIp->end_range];
		}

		return [
			'version' => time(),
			'data' => $data,
		];
	}

	public function rebuildDiscouragedIpCache()
	{
		$cache = $this->getDiscouragedIpCacheData();
		\XF::registry()->set('discouragedIps', $cache);
		return $cache;
	}

	protected function getIpRecord($ip)
	{
		$results = Ip::parseIpRangeString($ip);
		if (!$results)
		{
			throw new PrintableException(\XF::phrase('please_enter_valid_ip_or_ip_range'));
		}

		return [
			$results['printable'],
			$results['binary'][0],
			$results['startRange'],
			$results['endRange'],
		];
	}
}
