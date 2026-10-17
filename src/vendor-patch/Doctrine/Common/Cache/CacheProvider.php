<?php

namespace Doctrine\Common\Cache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * An adapter for providing Symfony cache adapters through the Doctrine cache
 * interface.
 */
class CacheProvider
{
	/**
	 * @var string
	 */
	public const DOCTRINE_NAMESPACE_CACHEKEY = 'DoctrineNamespaceCacheKey[%s]';

	/**
	 * @var AdapterInterface|null
	 */
	protected $adapter;

	public function __construct(?AdapterInterface $adapter = null)
	{
		$this->adapter = $adapter;
	}

	public function getAdapter(): ?AdapterInterface
	{
		return $this->adapter;
	}

	/**
	 * @param string $namesapce
	 */
	public function setNamespace($namespace)
	{
		return;
	}

	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return '';
	}

	/**
	 * @param string $id
	 *
	 * @return mixed
	 */
	public function fetch($id)
	{
		if (!$this->adapter)
		{
			return false;
		}

		$item = $this->adapter->getItem($id);
		if (!$item->isHit())
		{
			return false;
		}

		return $item->get();
	}

	/**
	 * @param list<string> $keys
	 *
	 * @return array<string, mixed>
	 */
	public function fetchMultiple(array $keys)
	{
		if (!$this->adapter)
		{
			return [];
		}

		$items = $this->adapter->getItems($keys);

		$results = [];

		foreach ($items AS $id => $item)
		{
			if (!$item->isHit())
			{
				continue;
			}

			$results[$id] = $item->get();
		}

		return $results;
	}

	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function contains($id)
	{
		if (!$this->adapter)
		{
			return false;
		}

		return $this->adapter->hasItem($id);
	}

	/**
	 * @param string $id
	 * @param mixed $data
	 * @param int $lifeTime
	 *
	 * @return bool
	 */
	public function save($id, $data, $lifeTime = 0)
	{
		if (!$this->adapter)
		{
			return false;
		}

		$item = $this->getCacheItemForSave($id, $data, $lifeTime);
		return $this->adapter->save($item);
	}

	/**
	 * @param array<string, mixed> $keysAndValues
	 * @param int $lifeTime
	 *
	 * @return bool
	 */
	public function saveMultiple(array $keysAndValues, $lifeTime = 0)
	{
		if (!$this->adapter)
		{
			return false;
		}

		foreach ($keysAndValues AS $id => $data)
		{
			$item = $this->getCacheItemForSave($id, $data, $lifeTime);
			$this->adapter->saveDeferred($item);
		}

		return $this->adapter->commit();
	}

	/**
	 * @param mixed $data
	 */
	protected function getCacheItemForSave(
		string $id,
		$data,
		int $lifeTime = 0
	): CacheItemInterface
	{
		if (!$this->adapter)
		{
			throw new \LogicException('Cannot get cache item without adapter');
		}

		$item = $this->adapter->getItem($id);

		$item->set($data);

		if ($lifeTime !== 0)
		{
			$item->expiresAfter($lifeTime);
		}

		return $item;
	}

	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function delete($id)
	{
		if (!$this->adapter)
		{
			return false;
		}

		return $this->adapter->deleteItem($id);
	}

	/**
	 * @param list<string> $keys
	 *
	 * @return bool
	 */
	public function deleteMultiple(array $keys)
	{
		if (!$this->adapter)
		{
			return false;
		}

		return $this->adapter->deleteItems($keys);
	}

	/**
	 * @return bool
	 */
	public function flushAll()
	{
		if (!$this->adapter)
		{
			return false;
		}

		return $this->adapter->clear();
	}

	/**
	 * @return bool
	 */
	public function deleteAll()
	{
		if (!$this->adapter)
		{
			return false;
		}

		return $this->adapter->clear();
	}

	/**
	 * @deprecated
	 *
	 * @return array<mixed>
	 */
	public function getStats()
	{
		trigger_error(
			'The cache getStats method has been deprecated',
			E_USER_DEPRECATED
		);

		return [];
	}
}
