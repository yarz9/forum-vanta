<?php

namespace XF\Session;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class CacheStorage implements StorageInterface
{
	/**
	 * @var AdapterInterface
	 */
	protected $cache;

	/**
	 * @var string
	 */
	protected $cacheIdPrefix;

	public function __construct(
		AdapterInterface $cache,
		string $cacheIdPrefix = 'session_'
	)
	{
		$this->cache = $cache;
		$this->cacheIdPrefix = $cacheIdPrefix;
	}

	public function getSession($sessionId)
	{
		try
		{
			$item = $this->cache->getItem($this->getCacheId($sessionId));
		}
		catch (InvalidArgumentException $e)
		{
			return false;
		}

		if (!$item->isHit())
		{
			return false;
		}

		return $item->get();
	}

	public function deleteSession($sessionId)
	{
		$this->cache->deleteItem($this->getCacheId($sessionId));
	}

	public function writeSession($sessionId, array $data, $lifetime, $existing)
	{
		$item = $this->cache->getItem($this->getCacheId($sessionId));
		$item->set($data);
		$item->expiresAfter($lifetime);
		$this->cache->save($item);
	}

	public function deleteExpiredSessions()
	{
		// this is expected to happen automatically
	}

	public function getCacheIdPrefix()
	{
		return $this->cacheIdPrefix;
	}

	public function setCacheIdPrefix($prefix)
	{
		$this->cacheIdPrefix = $prefix;
	}

	protected function getCacheId($sessionId)
	{
		return $this->cacheIdPrefix . $sessionId;
	}
}
