<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;
use XF\Util\Php;

use function count;

class CollectStatsRepository extends Repository
{
	public function getConfig()
	{
		$config = array_replace([
			'configured' => 0,
			'enabled' => 0,
			'installation_id' => '',
		], $this->options()->collectServerStats ?? []);

		return $config;
	}

	public function isEnabled()
	{
		$config = $this->getConfig();

		if ($config['configured'] && $config['enabled'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function collectStats()
	{
		if (!$this->isEnabled())
		{
			return [];
		}

		$config = $this->getConfig();

		$addOns = $this->app()->container('addon.cache');
		$firstPartyIds = [];

		foreach (array_keys($addOns) AS $addOnId)
		{
			// note: these are further filtered before being stored
			if (strpos($addOnId, 'XF') === 0)
			{
				$firstPartyIds[] = $addOnId;
				unset($addOns[$addOnId]);
			}
		}

		$thirdPartyIds = array_keys($addOns);

		return [
			'installation_id' => $config['installation_id'],
			'php' => $this->getPhpVersionString(),
			'mysql' => $this->getMySqlVersionString(),
			'mysql_compat' => $this->getCompatibleMySqlVersion(),
			'xf' => \XF::$version,
			'first_party_ids' => $firstPartyIds,
			'third_party_count' => count($thirdPartyIds),
		];
	}

	public function getPhpVersionString()
	{
		return phpversion();
	}

	public function getMySqlVersionString()
	{
		return $this->db()->getServerVersion();
	}

	public function getCompatibleMySqlVersion(): string
	{
		$db = $this->db();
		return Php::getCompatibleMySqlVersion(
			$db->getServerVersion(),
			$db->getConnection()->server_info
		);
	}
}
