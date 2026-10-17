<?php

namespace XF\SubContainer;

use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Service\ServiceInterface;
use OAuth\ServiceFactory;
use XF\ConnectedAccount\Http\Client;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\ConnectedAccount\Service\ProviderIdAwareInterface;
use XF\ConnectedAccount\Storage\Local;
use XF\ConnectedAccount\Storage\Session;
use XF\ConnectedAccount\Storage\StorageState;
use XF\Container;
use XF\Entity\ConnectedAccountProvider;
use XF\Entity\User;

class OAuth extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['client'] = function (Container $c)
		{
			$class = Client::class;
			$class = $this->extendClass($class);

			return $c->createObject($class);
		};

		$container['storage.session'] = function (Container $c)
		{
			$class = Session::class;
			$class = $this->extendClass($class);

			return $c->createObject($class, [$this->parent['session.public']]);
		};

		$container['storage.local'] = function (Container $c)
		{
			$class = Local::class;
			$class = $this->extendClass($class);

			return $c->createObject($class);
		};

		$container->factory('provider', function ($serviceName, array $params, Container $c)
		{
			if (isset($params['key']))
			{
				$config = $params;
				$providerId = '';
			}
			else
			{
				$config = $params[0];
				$providerId = $params[1];
			}

			/** @var Credentials $credentials */
			$credentials = $c->createObject(Credentials::class, [
				$config['key'], $config['secret'], $config['redirect'],
			]);

			/** @var ServiceFactory $serviceFactory */
			$serviceFactory = $c->createObject(ServiceFactory::class);

			$isClass = false;
			if (strpos($serviceName, ':') !== false)
			{
				$serviceName = \XF::stringToClass($serviceName, '\%s\ConnectedAccount\%s');
				$isClass = true;
			}
			else if (strpos($serviceName, '\\') !== false)
			{
				$isClass = true;
			}
			if ($isClass)
			{
				$serviceName = $this->extendClass($serviceName);
				$serviceFactory->registerService($serviceName, $serviceName);
			}

			/** @var Client $client */
			$client = $c['client'];
			$serviceFactory->setHttpClient($client);

			$storage = $this->storage($config['storageType']);

			$service = $serviceFactory->createService($serviceName, $credentials, $storage, $config['scopes']);

			if ($service instanceof ProviderIdAwareInterface)
			{
				$service->setProviderId($providerId);
			}

			return $service;
		});

		$container->factory('providerData', function ($class, array $params, Container $c)
		{
			$class = \XF::stringToClass($class, '%s\ConnectedAccount\%s');
			$class = $this->extendClass($class);
			return $c->createObject($class, $params);
		});
	}

	/**
	 * @return Session|Local
	 */
	public function storage($type = null)
	{
		return $type ? $this->container['storage.' . $type] : $this->container['storage.session'];
	}

	/**
	 * @template T of ServiceInterface
	 *
	 * @param class-string<T> $serviceName
	 * @param array $config
	 *
	 * @return T
	 */
	public function provider(string $serviceName, array $config = [], string $providerId = '')
	{
		return $this->container->create('provider', $serviceName, [$config, $providerId]);
	}

	/**
	 * @template T of AbstractProviderData
	 *
	 * @param class-string<T> $class
	 * @param $providerId
	 * @param StorageState $storageState
	 *
	 * @return T
	 */
	public function providerData($class, $providerId, StorageState $storageState)
	{
		return $this->container->create('providerData', $class, [$providerId, $storageState]);
	}

	/**
	 * @param ConnectedAccountProvider $provider
	 * @param User $user
	 *
	 * @return StorageState
	 */
	public function storageState(ConnectedAccountProvider $provider, User $user)
	{
		$class = $this->extendClass(StorageState::class);
		return new $class($provider, $user);
	}

	/**
	 * @return Client
	 */
	public function client()
	{
		return $this->container['client'];
	}
}
