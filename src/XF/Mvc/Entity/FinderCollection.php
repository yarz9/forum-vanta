<?php

namespace XF\Mvc\Entity;

/**
 * @template T of \XF\Mvc\Entity\Entity
 * @extends AbstractCollection<T>
 */
class FinderCollection extends AbstractCollection
{
	/**
	 * @var Finder
	 */
	protected $baseFinder;

	protected $keyField;

	protected $entities = [];
	protected $falseEntities = [];

	public function __construct(Finder $baseFinder, $keyField, array $entities = [], array $falseEntities = [])
	{
		$this->baseFinder = $baseFinder;
		$this->keyField = $keyField;
		$this->entities = $entities;
		$this->falseEntities = $falseEntities;
	}

	protected function populateInternal()
	{
		$keyField = $this->keyField;
		$this->entities = [];

		foreach ($this->baseFinder->fetch() AS $entity)
		{
			$this->entities[$entity->$keyField] = $entity;
		}

		$this->falseEntities = [];

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return T|null
	 */
	public function offsetGet($key): ?Entity
	{
		if (isset($this->entities[$key]))
		{
			return $this->entities[$key];
		}

		if (isset($this->falseEntities[$key]))
		{
			return null;
		}

		if (!$this->populated)
		{
			$finder = clone $this->baseFinder;
			$finder->where($this->keyField, $key);
			$value = $finder->fetchOne();
			if ($value)
			{
				$this->entities[$key] = $value;
			}
			else
			{
				$this->falseEntities[$key] = true;
			}

			return $value;
		}

		return null;
	}

	/**
	 * Forcibly inserts the specified entity in the cache. This is an advanced function and should be
	 * used sparingly.
	 *
	 * @param T $entity
	 */
	public function forceCache(Entity $entity)
	{
		$keyField = $this->keyField;
		$key = $entity->$keyField;

		$this->entities[$key] = $entity;
		unset($this->falseEntities[$key]);
	}

	/**
	 * Forces the specified key to be removed from the collection and to be considered "not found" for future
	 * lookups. This is an advanced function and should be used sparingly.
	 *
	 * @param mixed $key
	 */
	public function forceRemove($key)
	{
		$this->falseEntities[$key] = true;
		unset($this->entities[$key]);
	}

	public function offsetSet($key, $value): void
	{
		throw new \LogicException("Finder collections are not writable");
	}

	public function offsetExists($key): bool
	{
		return $this->offsetGet($key) !== null;
	}

	public function offsetUnset($key): void
	{
		throw new \LogicException("Finder collections are not writable");
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
