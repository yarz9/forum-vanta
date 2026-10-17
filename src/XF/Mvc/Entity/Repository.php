<?php

namespace XF\Mvc\Entity;

use XF\App;
use XF\Db\AbstractAdapter;
use XF\Options;

abstract class Repository
{
	/**
	 * @var Manager
	 */
	protected $em;

	protected $identifier;

	public function __construct(Manager $em, $identifier)
	{
		$this->em = $em;

		if (substr($identifier, -10) === 'Repository')
		{
			$identifier = substr($identifier, 0, -10);
		}
		$this->identifier = $identifier;
	}

	/**
	 * @return AbstractAdapter
	 */
	public function db()
	{
		return $this->em->getDb();
	}

	/**
	 * @template T of Finder
	 *
	 * @param class-string<T> $identifier
	 *
	 * @return T
	 */
	public function finder($identifier)
	{
		return $this->em->getFinder($identifier);
	}

	/**
	 * @template T of Repository
	 *
	 * @param class-string<T> $identifier
	 *
	 * @return T
	 */
	public function repository($identifier)
	{
		return $this->em->getRepository($identifier);
	}

	/**
	 * @return App
	 */
	public function app()
	{
		return \XF::app();
	}

	/**
	 * @return Options
	 */
	public function options()
	{
		return $this->app()->options();
	}

	public function __sleep()
	{
		throw new \LogicException('Instances of ' . self::class . ' cannot be serialized or unserialized');
	}

	public function __wakeup()
	{
		throw new \LogicException('Instances of ' . self::class . ' cannot be serialized or unserialized');
	}
}
