<?php

namespace XF\SubContainer;

use Laminas\Mail\Storage\AbstractStorage;
use XF\Container;
use XF\EmailUnsubscribe\Processor;

class Unsubscribe extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container->set('storage', function (Container $c)
		{
			return Processor::getDefaultUnsubscribeHandlerStorage($this->app);
		}, false);

		$container['processor'] = function (Container $c)
		{
			$options = $this->app->options();
			$unsubscribeEmailAddress = $options->unsubscribeEmailAddress;

			$class = $this->app->extendClass(Processor::class);

			return new $class($this->app, $options->enableVerp ? $unsubscribeEmailAddress : null);
		};
	}

	/**
	 * @return AbstractStorage
	 */
	public function storage()
	{
		return $this->container['storage'];
	}

	/**
	 * @return Processor
	 */
	public function processor()
	{
		return $this->container['processor'];
	}
}
