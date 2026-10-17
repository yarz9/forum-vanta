<?php

namespace XF\SubContainer;

use XF\App;
use XF\Container;

abstract class AbstractSubContainer implements \ArrayAccess
{
	/**
	 * @var Container
	 */
	protected $parent;

	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var Container
	 */
	protected $container;

	abstract public function initialize();

	public function __construct(Container $parent, App $app)
	{
		$this->parent = $parent;
		$this->app = $app;

		$this->container = new Container();
		$this->initialize();
	}

	/**
	 * Gets the callable class name for a dynamically extended class.
	 *
	 * @template TBase
	 * @template TFakeBase
	 * @template TSubclass of TBase
	 *
	 * @param class-string<TBase>          $class
	 * @param class-string<TFakeBase>|null $fakeBaseClass
	 *
	 * @return class-string<TSubclass>
	 */
	public function extendClass($class, $fakeBaseClass = null)
	{
		return $this->app->extendClass($class, $fakeBaseClass);
	}

	/**
	 * @param string $key
	 * @param \Closure $rebuildFunction
	 * @param \Closure|null $decoratorFunction
	 *
	 * @return \Closure
	 */
	public function fromRegistry($key, \Closure $rebuildFunction, ?\Closure $decoratorFunction = null)
	{
		return $this->app->fromRegistry($key, $rebuildFunction, $decoratorFunction);
	}

	public function get($key)
	{
		return $this->container->offsetGet($key);
	}

	/**
	 * @param mixed $key
	 *
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($key)
	{
		return $this->container->offsetGet($key);
	}

	/**
	 * @param mixed $key
	 * @param mixed $value
	 */
	public function offsetSet($key, $value): void
	{
		$this->container->offsetSet($key, $value);
	}

	/**
	 * @param mixed $key
	 *
	 * @return bool
	 */
	public function offsetExists($key): bool
	{
		return $this->container->offsetExists($key);
	}

	/**
	 * @param mixed $key
	 */
	public function offsetUnset($key): void
	{
		$this->container->offsetUnset($key);
	}

	/**
	 * @param string|null $key
	 *
	 * @return Container|mixed
	 */
	public function container($key = null)
	{
		return $key === null ? $this->container : $this->container[$key];
	}
}
