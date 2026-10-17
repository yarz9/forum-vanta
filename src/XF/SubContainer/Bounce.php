<?php

namespace XF\SubContainer;

use Laminas\Mail\Storage\AbstractStorage;
use XF\Container;
use XF\EmailBounce\Parser;
use XF\EmailBounce\Processor;

class Bounce extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container->set('storage', function (Container $c)
		{
			return Processor::getDefaultBounceHandlerStorage($this->app);
		}, false);

		$container['parser'] = function (Container $c)
		{
			$options = $this->app->options();

			$class = $this->app->extendClass(Parser::class);

			return new $class(
				$options->enableVerp ? $options->bounceEmailAddress : null,
				$this->app->config('globalSalt')
			);
		};

		$container['processor'] = function (Container $c)
		{
			$class = $this->app->extendClass(Processor::class);

			return new $class($this->app, $c['parser']);
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
	 * @return Parser
	 */
	public function parser()
	{
		return $this->container['parser'];
	}

	/**
	 * @return Processor
	 */
	public function processor()
	{
		return $this->container['processor'];
	}
}
