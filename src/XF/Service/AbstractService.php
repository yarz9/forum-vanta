<?php

namespace XF\Service;

use XF\App;
use XF\Db\AbstractAdapter;
use XF\Mvc\Entity\Manager;

use function call_user_func_array, func_get_args;

abstract class AbstractService
{
	/**
	 * @var App
	 */
	protected $app;

	public function __construct(App $app)
	{
		$this->app = $app;
		$this->setup();
	}

	protected function setup()
	{
	}

	/**
	 * @return AbstractAdapter
	 */
	protected function db()
	{
		return $this->app->db();
	}

	/**
	 * @return Manager
	 */
	protected function em()
	{
		return $this->app->em();
	}

	/**
	 * @template T of \XF\Mvc\Entity\Repository
	 *
	 * @param class-string<T> $repository
	 *
	 * @return T
	 */
	protected function repository($repository)
	{
		return $this->app->repository($repository);
	}

	/**
	 * @template T of \XF\Mvc\Entity\Finder
	 *
	 * @param class-string<T> $finder
	 *
	 * @return T
	 */
	protected function finder($finder)
	{
		return $this->app->finder($finder);
	}

	/**
	 * @template T of \XF\Mvc\Entity\Entity
	 *
	 * @param class-string<T> $finder
	 * @param array $where
	 * @param array|string|null $with
	 *
	 * @return T|null
	 */
	protected function findOne($finder, array $where, $with = null)
	{
		return $this->app->em()->findOne($finder, $where, $with);
	}

	/**
	 * @template T of \XF\Service\AbstractService
	 *
	 * @param class-string<T> $class
	 * @param mixed ...$arguments
	 *
	 * @return T
	 */
	public function service($class)
	{
		return call_user_func_array([$this->app, 'service'], func_get_args());
	}
}
