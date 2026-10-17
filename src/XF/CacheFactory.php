<?php

namespace XF;

use Doctrine\Common\Cache\CacheProvider;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

use function call_user_func, in_array, intval, is_array, is_string;

class CacheFactory
{
	/**
	 * @var string
	 */
	protected $namespace = '';

	public function __construct(string $namespace = '')
	{
		$this->namespace = $namespace;
	}

	public function setNamespace(string $namespace): void
	{
		$this->namespace = $namespace;
	}

	public function getNamespace(): string
	{
		return $this->namespace;
	}

	/**
	 * @param string|\Closure(array): AdapterInterface $provider
	 * @param array<string, mixed> $config
	 *
	 * @return CacheProvider|AbstractAdapter|null
	 */
	public function create(
		$provider,
		array $config = [],
		bool $doctrineCompatible = true
	)
	{
		$cache = $this->instantiate($provider, $config);
		if ($cache === null)
		{
			return null;
		}

		if ($doctrineCompatible)
		{
			return new CacheProvider($cache);
		}

		return $cache;
	}

	/**
	 * @param string|\Closure(array): AdapterInterface $provider
	 * @param array<string, mixed> $config
	 */
	protected function instantiate(
		$provider,
		array $config = []
	): ?AdapterInterface
	{
		// factory closure
		if ($provider instanceof \Closure)
		{
			$cache = $provider($config);
			if (!($cache instanceof AdapterInterface))
			{
				$this->logDeprecatedCacheProvider('Closure');
				return null;
			}

			return $cache;
		}

		// class\name or class\name::method
		if (is_string($provider) && strpos($provider, '\\') !== false)
		{
			$parts = explode('::', $provider);
			if (isset($parts[1]))
			{
				// class::method - assume this is the factory method
				$cache = call_user_func($parts, $config);
			}
			else
			{
				// assume this is the provider itself
				$cache = new $provider($config);
			}

			if (!($cache instanceof AdapterInterface))
			{
				$this->logDeprecatedCacheProvider($provider);
				return null;
			}

			return $cache;
		}

		// provider name only, which we'll map to a method
		if (is_string($provider) && $provider)
		{
			if (in_array($provider, ['Apc', 'Void', 'WinCache', 'XCache']))
			{
				$this->logDeprecatedCacheProvider($provider);
				return null;
			}

			$method = 'create' . $provider . 'Cache';
			if (!is_callable([$this, $method]))
			{
				throw new \InvalidArgumentException(
					"Invalid cache provider '$provider'"
				);
			}

			return $this->$method($config);
		}

		throw new \InvalidArgumentException('Invalid type of cache provider');
	}

	protected function logDeprecatedCacheProvider(string $provider): void
	{
		// log an error once per day per provider
		$registryKey = substr('depCache_' . md5($provider), 0, 25);
		\XF::runOnce($registryKey, function () use ($provider, $registryKey)
		{
			$registry = \XF::registry();
			$time = $registry->get($registryKey);
			if ($time !== null && $time >= \XF::$time - 86400)
			{
				return;
			}

			\XF::logError('Deprecated cache provider: ' . $provider);
			$registry->set($registryKey, \XF::$time);
		});
	}

	/**
	 * @param array<string, mixed> $config
	 */
	protected function createApcuCache(array $config): ApcuAdapter
	{
		return new ApcuAdapter($this->namespace);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	protected function createFilesystemCache(array $config): FilesystemAdapter
	{
		if (empty($config['directory']))
		{
			throw new \LogicException(
				"Filesystem cache config must define a 'directory'"
			);
		}

		$cache = new FilesystemAdapter(
			$this->namespace,
			0,
			$config['directory']
		);

		$cleanFrequency = max(
			!empty($config['clean']) ? intval($config['clean']) : 1000,
			2
		);
		if (random_int(1, $cleanFrequency) === 1)
		{
			$cache->prune();
		}

		return $cache;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	protected function createMemcachedCache(array $config): MemcachedAdapter
	{
		if (empty($config['servers']))
		{
			if (!empty($config['server']))
			{
				// legacy format
				if (is_array($config['server']))
				{
					$config['servers'] = $config['server'];
				}
				else
				{
					$config['servers'] = [['host' => $config['server']]];
				}

				unset($config['server']);
			}
			else
			{
				// default value
				$config['servers'] = [['host' => 'localhost']];
			}
		}

		$servers = [];
		foreach ($config['servers'] AS $server)
		{
			if (isset($server[0]))
			{
				// legacy format
				$server = [
					'host' => $server[0],
					'port' => $server[1] ?? null,
					'weight' => $server[2] ?? null,
				];
			}

			$dsn = 'memcached://';

			$user = $server['user'] ?? null;
			$pass = $server['password'] ?? null;
			if ($user !== null && $pass !== null)
			{
				$dsn .= rawurlencode($user) . ':' . rawurlencode($pass) . '@';
			}

			$dsn .= $server['host'];

			$port = $server['port'] ?? null;
			if ($port !== null)
			{
				$dsn .= ':' . $port;
			}

			$weight = $server['weight'] ?? null;
			if ($weight !== null)
			{
				$dsn .= '?weight=' . $weight;
			}

			$servers[] = $dsn;
		}

		$custom = $config['custom'] ?? null;

		unset($config['servers'], $config['custom']);

		$memcached = MemcachedAdapter::createConnection(
			$servers,
			$config
		);

		if ($custom !== null && $custom instanceof \Closure)
		{
			$custom($memcached);
		}

		return new MemcachedAdapter($memcached, $this->namespace);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	protected function createNullCache(array $config): NullAdapter
	{
		return new NullAdapter();
	}

	/**
	 * @param array<string, mixed> $config
	 */
	protected function createRedisCache(array $config): RedisAdapter
	{
		if (empty($config['host']))
		{
			throw new \LogicException("Redis cache config must define a 'host'");
		}

		$dsn = 'redis://';

		$pass = $config['password'] ?? null;
		if ($pass !== null)
		{
			$dsn .= rawurlencode($pass) . '@';
		}

		$dsn .= $config['host'];

		$port = $config['port'] ?? null;
		if (strpos($config['host'], '/') === false && $port !== null)
		{
			$dsn .= ':' . $port;
		}

		$dbindex = $config['database'] ?? null;
		if ($dbindex !== null)
		{
			$dsn .= '/' . $dbindex;
		}

		$custom = $config['custom'] ?? null;

		unset(
			$config['password'],
			$config['host'],
			$config['port'],
			$config['database'],
			$config['custom']
		);

		$redis = RedisAdapter::createConnection($dsn, $config);

		if ($custom !== null && $custom instanceof \Closure)
		{
			$custom($redis);
		}

		return new RedisAdapter($redis, $this->namespace);
	}
}
