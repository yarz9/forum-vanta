<?php

namespace XF\Mvc\Entity;

use XF\Api\Result\EntityResultInterface;
use XF\Api\Result\EntityResults;

use function array_key_exists, array_slice, count, func_get_args, is_array;

/**
 * @template T of \XF\Mvc\Entity\Entity
 * @implements \IteratorAggregate<string|int, T>
 * @implements \ArrayAccess<string|int, T>
 */
abstract class AbstractCollection implements \Countable, \IteratorAggregate, \ArrayAccess
{
	/**
	 * @var T[]
	 */
	protected $entities = [];

	/**
	 * @var bool
	 */
	protected $populated = false;

	abstract protected function populateInternal();

	public function populate()
	{
		if (!$this->populated)
		{
			$this->populated = true;
			$this->populateInternal();
		}

		return $this;
	}

	/**
	 * @return array<int|string,T>
	 */
	public function toArray()
	{
		$this->populate();

		return $this->entities;
	}

	public function toApiResults($verbosity = Entity::VERBOSITY_NORMAL, array $options = [], $maintainKeys = false)
	{
		$this->populate();

		$results = [];

		foreach ($this->entities AS $k => $entity)
		{
			$result = $entity->toApiResult($verbosity, $options);
			if ($maintainKeys)
			{
				$results[$k] = $result;
			}
			else
			{
				$results[] = $result;
			}
		}

		return new EntityResults($results);
	}

	public function toWebhookResults(array $options = [], $maintainKeys = false): EntityResults
	{
		$this->populate();

		$results = [];

		foreach ($this->entities AS $k => $entity)
		{
			$result = $entity->toWebhookResult($options);
			if ($maintainKeys)
			{
				$results[$k] = $result;
			}
			else
			{
				$results[] = $result;
			}
		}

		return new EntityResults(
			$results,
			EntityResultInterface::TYPE_WEBHOOK
		);
	}

	/**
	 * @param int|string $key
	 *
	 * @return T|null
	 */
	public function offsetGet($key): ?Entity
	{
		return $this->entities[$key];
	}

	/**
	 * @param int|string $key
	 * @param T $value
	 * @return void
	 */
	public function offsetSet($key, $value): void
	{
		$this->entities[$key] = $value;
	}

	/**
	 * @param int|string $key
	 * @return bool
	 */
	public function offsetExists($key): bool
	{
		return isset($this->entities[$key]);
	}

	/**
	 * @param int|string $key
	 */
	public function offsetUnset($key): void
	{
		unset($this->entities[$key]);
	}

	/**
	 * @return \ArrayIterator<string|int, T>
	 */
	public function getIterator(): \ArrayIterator
	{
		$this->populate();

		return new \ArrayIterator($this->entities);
	}

	public function count(): int
	{
		$this->populate();

		return count($this->entities);
	}

	public function keys()
	{
		$this->populate();

		return array_keys($this->entities);
	}

	/**
	 * @return false|T
	 */
	public function first()
	{
		$this->populate();

		return reset($this->entities);
	}

	/**
	 * @return false|T
	 */
	public function last()
	{
		$this->populate();

		return end($this->entities);
	}

	/**
	 * @param callable $callback
	 * @param bool $collectionOnEmpty If true, an empty plucking will return a collection; otherwise, an array
	 *
	 * @return array<T>|AbstractCollection<T>
	 */
	public function pluck(\Closure $callback, $collectionOnEmpty = true)
	{
		$this->populate();

		$output = [];
		$newCollection = true;

		foreach ($this->entities AS $key => $entity)
		{
			$res = $callback($entity, $key);
			if (is_array($res))
			{
				$output[$res[0]] = $res[1];

				if (!($res[1] instanceof Entity))
				{
					$newCollection = false;
				}
			}
		}

		if (!$output)
		{
			return $collectionOnEmpty ? new ArrayCollection([]) : [];
		}
		else
		{
			return $newCollection ? new ArrayCollection($output) : $output;
		}
	}

	public function pluckNamed($valueField, $keyField = null)
	{
		$i = 0;
		$f = function (Entity $e) use ($keyField, $valueField, &$i)
		{
			if ($keyField !== null)
			{
				$key = $e->$keyField;
			}
			else
			{
				$key = $i;
				$i++;
			}

			$value = $e->$valueField;

			return [$key, $value];
		};

		// starts with upper case letter means pulling an entity so give a collection (by convention)
		$collectionOnEmpty = preg_match('/^[A-Z]/', $valueField);

		return $this->pluck($f, $collectionOnEmpty);
	}

	/**
	 * @param callable(T $entity): bool $callback
	 *
	 * @return AbstractCollection<T>
	 */
	public function filter(\Closure $callback)
	{
		return new ArrayCollection(array_filter($this->toArray(), $callback));
	}

	/**
	 * Applys the equivalent of array_slice to a collection
	 *
	 * @param int $offset
	 * @param null|int $length
	 * @param bool $preserveKeys
	 *
	 * @return AbstractCollection<T>
	 */
	public function slice($offset, $length = null, $preserveKeys = true)
	{
		return new ArrayCollection(array_slice($this->toArray(), $offset, $length, $preserveKeys));
	}

	/**
	 * @param int $page
	 * @param int $perPage
	 * @param bool $preserveKeys
	 * @return AbstractCollection<T>
	 */
	public function sliceToPage($page, $perPage, $preserveKeys = true)
	{
		return $this->slice(($page - 1) * $perPage, $perPage, $preserveKeys);
	}

	/**
	 * Returns a new collection with the argument merged.
	 * Existing elements will be before the other collection's elements.
	 *
	 * @param AbstractCollection<T> $other
	 *
	 * @return AbstractCollection<T>
	 */
	public function merge(AbstractCollection $other)
	{
		$elements = $this->toArray();
		foreach ($other->toArray() AS $k => $v)
		{
			$elements[$k] = $v;
		}

		return new ArrayCollection($elements);
	}

	/**
	 * @return AbstractCollection<T>
	 */
	public function reverse($preserveKeys = true)
	{
		return new ArrayCollection(array_reverse($this->toArray(), $preserveKeys));
	}

	/**
	 * Given a list of ordered keys in a collection, return a new collection
	 * sorted in that order. Ignores keys without corresponding values in the collection.
	 *
	 * @param array $keys
	 *
	 * @return AbstractCollection<T>
	 */
	public function sortByList(array $keys)
	{
		$values = [];
		$elements = $this->toArray();

		foreach ($keys AS $key)
		{
			if (array_key_exists($key, $elements))
			{
				$values[$key] = $elements[$key];
			}
		}

		return new ArrayCollection($values);
	}

	/**
	 * @return AbstractCollection<T>
	 */
	public function pop()
	{
		$entities = $this->toArray();
		array_pop($entities);
		return new ArrayCollection($entities);
	}

	/**
	 * Returns the nth item of the collection, where the first item is 0
	 *
	 * @param $n
	 *
	 * @return false|T
	 */
	public function nth($n)
	{
		$slice = array_slice($this->toArray(), $n - 1, 1);
		return reset($slice);
	}

	/**
	 * @return AbstractCollection<T>
	 */
	public function shuffle()
	{
		$entities = $this->toArray();
		shuffle($entities);
		return new ArrayCollection($entities);
	}

	/**
	 * @return T|AbstractCollection<T>|null
	 */
	public function shift($returnType = 'entity')
	{
		$entities = $this->toArray();
		$shifted = array_shift($entities);

		if ($shifted)
		{
			if ($returnType == 'entity')
			{
				return $shifted;
			}
			else
			{
				return new ArrayCollection($entities);
			}
		}
		else
		{
			return null;
		}
	}

	/**
	 * @return AbstractCollection<T>
	 */
	public function unshift()
	{
		$args = array_reverse(func_get_args());
		$entities = $this->toArray();
		foreach ($args AS $arg)
		{
			array_unshift($entities, $arg);
		}
		return new ArrayCollection($entities);
	}

	/**
	 * @return AbstractCollection<T>
	 */
	public function filterViewable()
	{
		return $this->filter(function ($entity)
		{
			// TODO: ideally type hint the viewable interface
			return $entity->canView();
		});
	}

	/**
	 * @param int|string|callable(T $e): int|string $grouper
	 * @param int|string|callable(T $e): int|string $childKeyFn
	 * @return array<int|string, array<int|string,T>>
	 */
	public function groupBy($grouper, $childKeyFn = null)
	{
		$this->populate();

		if ($grouper instanceof \Closure)
		{
			$callback = $grouper;
		}
		else
		{
			$callback = function ($e) use ($grouper) { return $e[$grouper]; };
		}

		if ($childKeyFn && !($childKeyFn instanceof \Closure))
		{
			$childKey = $childKeyFn;
			$childKeyFn = function ($e) use ($childKey) { return $e[$childKey]; };
		}

		$output = [];
		foreach ($this->entities AS $key => $entity)
		{
			$groupKey = $callback($entity);
			if ($childKeyFn)
			{
				$key = $childKeyFn($entity);
			}
			$output[$groupKey][$key] = $entity;
		}

		return $output;
	}
}
