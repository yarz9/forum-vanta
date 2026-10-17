<?php

namespace XF;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use XF\Db\AbstractAdapter;

use function array_key_exists, is_array;

class DataRegistry implements \ArrayAccess
{
	/**
	 * @var AbstractAdapter
	 */
	protected $db;

	/**
	 * @var AdapterInterface|null
	 */
	protected $cache;

	/**
	 * @var string
	 */
	protected $cacheIdPrefix = 'data_';

	/**
	 * @var int
	 */
	protected $cacheLifeTime = 3600;

	/**
	 * @var mixed[]
	 */
	protected $localData = [];

	public function __construct(
		AbstractAdapter $db,
		?AdapterInterface $cache = null
	)
	{
		$this->db = $db;
		$this->cache = $cache;
	}

	public function getCacheIdPrefix(): string
	{
		return $this->cacheIdPrefix;
	}

	public function setCacheIdPrefix(string $prefix): void
	{
		$this->cacheIdPrefix = $prefix;
	}

	public function getCacheLifeTime(): int
	{
		return $this->cacheLifeTime;
	}

	public function setCacheLifeTime(int $lifeTime): void
	{
		$this->cacheLifeTime = $lifeTime;
	}

	/**
	 * @param string[]|string $keys
	 *
	 * @return mixed|mixed[]
	 */
	public function get($keys)
	{
		if (!is_array($keys))
		{
			$keys = [$keys];
			$isMulti = false;
		}
		else
		{
			if (!$keys)
			{
				return [];
			}

			$isMulti = true;
		}

		$data = [];
		$originalKeys = $keys;
		foreach ($keys AS $i => $key)
		{
			if (array_key_exists($key, $this->localData))
			{
				$data[$key] = $this->localData[$key];
				unset($keys[$i]);
			}
		}

		if ($keys)
		{
			$remainingKeys = $this->readFromCache($keys, $data);
			$this->readFromDb($remainingKeys, $data);
		}

		if ($isMulti)
		{
			return $data;
		}
		else
		{
			return $data[reset($originalKeys)];
		}
	}

	public function exists(string $key): bool
	{
		return ($this->get($key) !== null);
	}

	/**
	 * @param string[]|string $keys
	 * @param mixed[] $data
	 *
	 * @return string[]|string
	 */
	protected function readFromCache(array $keys, array &$data): array
	{
		if (!$this->cache || !$keys)
		{
			return $keys;
		}

		$lookups = [];
		foreach ($keys AS $i => $key)
		{
			$lookups[$this->getCacheId($key)] = [$i, $key];
		}

		$items = $this->cache->getItems(array_keys($lookups));
		foreach ($items AS $cacheKey => $item)
		{
			if (!$item->isHit())
			{
				continue;
			}

			$keyId = $lookups[$cacheKey][0];
			$keyName = $lookups[$cacheKey][1];
			unset($keys[$keyId]); // don't need to read from the DB

			$data[$keyName] = $item->get();
			$this->localData[$keyName] = $data[$keyName];
		}

		return $keys;
	}

	/**
	 * @param string[]|string $keys
	 * @param mixed[] $data
	 */
	protected function readFromDb(array $keys, array &$data): void
	{
		if (!$keys)
		{
			return;
		}

		$pairs = $this->db->fetchPairs("
			SELECT data_key, data_value
			FROM xf_data_registry
			WHERE data_key IN (" . $this->db->quote($keys) . ")
		");
		foreach ($keys AS $key)
		{
			$exists = false;

			if (isset($pairs[$key]))
			{
				$value = @unserialize($pairs[$key]);
				if ($value !== false || $pairs[$key] === 'b:0;')
				{
					$data[$key] = $value;
					$exists = true;
				}
			}

			if ($exists)
			{
				// populate the cache on demand
				$this->setInCache($key, $data[$key]);
			}
			else
			{
				$data[$key] = null;
			}

			$this->localData[$key] = $data[$key];
		}
	}

	/**
	 * @param mixed $value
	 */
	public function set(string $key, $value): void
	{
		$this->db->query("
			INSERT INTO xf_data_registry
				(data_key, data_value)
			VALUES
				(?, ?)
			ON DUPLICATE KEY UPDATE
				data_value = VALUES(data_value)
		", [$key, serialize($value)]);

		$this->setInCache($key, $value);

		$this->localData[$key] = $value;
	}

	/**
	 * @param mixed $value
	 */
	protected function setInCache(string $key, $value): void
	{
		if ($this->cache)
		{
			$item = $this->cache->getItem($this->getCacheId($key));
			$item->set($value);
			$item->expiresAfter($this->cacheLifeTime);
			$this->cache->save($item);
		}
	}

	/**
	 * @param string[]|string $keys
	 */
	public function delete($keys): void
	{
		if (!is_array($keys))
		{
			$keys = [$keys];
		}
		else if (!$keys)
		{
			return;
		}

		$this->db->delete('xf_data_registry', 'data_key IN (' . $this->db->quote($keys) . ')');

		if ($this->cache)
		{
			$cacheIds = array_map([$this, 'getCacheId'], $keys);
			$this->cache->deleteItems($cacheIds);
		}

		foreach ($keys AS $key)
		{
			$this->localData[$key] = null;
		}
	}

	protected function getCacheId(string $key): string
	{
		return $this->cacheIdPrefix . $key;
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($key)
	{
		return $this->get($key);
	}

	public function offsetSet($key, $value): void
	{
		$this->set($key, $value);
	}

	public function offsetUnset($key): void
	{
		$this->delete($key);
	}

	public function offsetExists($key): bool
	{
		return $this->exists($key);
	}
}
