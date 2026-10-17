<?php

namespace XF\SubContainer;

use XF\Oembed\Controller;

class Oembed extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['controller'] = function ($c)
		{
			return new Controller($this->app, $this->app->request());
		};
	}

	/**
	 * @return Controller
	 */
	public function controller()
	{
		return $this->container['controller'];
	}
}
