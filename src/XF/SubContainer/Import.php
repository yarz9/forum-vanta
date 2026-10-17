<?php

namespace XF\SubContainer;

use XF\Container;
use XF\Import\Manager;

class Import extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['manager'] = function (Container $c)
		{
			return new Manager($this->app, $c['importers']);
		};

		$container['importers'] = function ()
		{
			$importers = [];

			$this->app->fire('import_importer_classes', [$this, $this->parent, &$importers]);

			return $importers;
		};
	}

	/**
	 * @return Manager
	 */
	public function manager()
	{
		return $this->container['manager'];
	}
}
