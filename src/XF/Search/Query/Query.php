<?php

namespace XF\Search\Query;

use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\Search;

use function intval, is_array, is_string;

class Query
{
	/**
	 * @var Search
	 */
	protected $search;

	/**
	 * @var AbstractData|null
	 */
	protected $handler = null;

	/**
	 * @var string|null
	 */
	protected $handlerType = null;

	/**
	 * @var list<string>
	 */
	protected $types = null;

	/**
	 * @var bool|null
	 */
	protected $allowHidden = null;

	/**
	 * @var list<int>
	 */
	protected $userIds = [];

	/**
	 * @var int
	 */
	protected $maxDate = 0;

	/**
	 * @var int
	 */
	protected $minDate = 0;

	/**
	 * @var list<MetadataConstraintInterface>
	 */
	protected $metadataConstraints = [];

	/**
	 * @var array<string, array{types: list<string>, constraints: list<MetadataConstraintInterface>}>
	 */
	protected $permissionConstraints = [];

	/**
	 * @var list<SqlConstraint>
	 */
	protected $sqlConstraints = [];

	/**
	 * @var string|null
	 */
	protected $groupByType = null;

	/**
	 * @var string|SqlOrder
	 */
	protected $order = 'date';

	/**
	 * @var string
	 */
	protected $orderName = 'date';

	/**
	 * @var array<string, string>
	 */
	protected $errors = [];

	/**
	 * @var array<string, string>
	 */
	protected $warnings = [];

	public function __construct(Search $search)
	{
		$this->search = $search;

		$this->orderedBy($search->isRelevanceSupported() ? 'relevance' : 'date');
	}

	/**
	 * @template T of Entity
	 *
	 * @param AbstractData<T> $handler
	 * @param array<string, mixed> $urlConstraints
	 *
	 * @return $this
	 */
	public function forTypeHandler(AbstractData $handler, Request $request, array &$urlConstraints = [])
	{
		$this->forTypeHandlerBasic($handler);

		$handler->applyTypeConstraintsFromInput($this, $request, $urlConstraints);

		return $this;
	}

	public function forTypeHandlerBasic(AbstractData $handler)
	{
		$this->handler = $handler;
		$this->handlerType = $handler->getContentType();
		$this->types = $handler->getSearchableContentTypes();
	}

	/**
	 * @return string
	 */
	public function getHandlerType()
	{
		return $this->handlerType;
	}

	/**
	 * @return AbstractData
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * @param string|list<string> $type
	 */
	public function inType($type)
	{
		return $this->inTypes(is_array($type) ? $type : [$type]);
	}

	/**
	 * @param list<string> $types
	 *
	 * @return $this
	 */
	public function inTypes(array $types)
	{
		$this->types = $types;

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getTypes()
	{
		return $this->types;
	}

	/**
	 * @param bool|null $allow
	 *
	 * @return $this
	 */
	public function allowHidden($allow = true)
	{
		$this->allowHidden = $allow === null ? $allow : (bool) $allow;

		return $this;
	}

	/**
	 * @return bool|null
	 */
	public function getAllowHidden()
	{
		return $this->allowHidden;
	}

	/**
	 * @param int $userId
	 *
	 * @return $this
	 */
	public function byUserId($userId)
	{
		return $this->byUserIds([$userId]);
	}

	/**
	 * @param list<int> $userIds
	 *
	 * @return $this
	 */
	public function byUserIds(array $userIds)
	{
		$idsFiltered = [];
		foreach ($userIds AS $id)
		{
			$id = intval($id);
			if ($id > 0)
			{
				$idsFiltered[] = $id;
			}
		}

		if (!$idsFiltered)
		{
			throw new \InvalidArgumentException("No valid users to limit search to");
		}

		$this->userIds = $idsFiltered;

		return $this;
	}

	/**
	 * @return list<int>
	 */
	public function getUserIds()
	{
		return $this->userIds;
	}

	/**
	 * @param int $min
	 * @param int $max
	 *
	 * @return $this
	 */
	public function withinDateRange($min, $max)
	{
		$min = intval($min);
		$max = intval($max);

		if ($max > $min)
		{
			throw new \InvalidArgumentException("Max date must be greater than min");
		}

		$this->minDate = $min;
		$this->maxDate = $max;

		return $this;
	}

	/**
	 * @param int $min
	 *
	 * @return $this
	 */
	public function newerThan($min)
	{
		$this->minDate = max(0, intval($min));

		return $this;
	}

	/**
	 * @param int $max
	 *
	 * @return $this
	 */
	public function olderThan($max)
	{
		$this->maxDate = max(0, intval($max));

		return $this;
	}

	/**
	 * @return int
	 */
	public function getMinDate()
	{
		return $this->minDate;
	}

	/**
	 * @return int
	 */
	public function getMaxDate()
	{
		return $this->maxDate;
	}

	/**
	 * @param list<int>|int $tags
	 * @param string|int $match
	 *
	 * @return $this
	 */
	public function withTags($tags, $match = 'all')
	{
		if (!is_array($tags))
		{
			$tags = [$tags];
		}
		$tags = array_map('intval', $tags);
		$tags = array_unique($tags);
		if ($tags)
		{
			$this->withMetadata('tag', $tags, $match);
		}

		return $this;
	}

	/**
	 * @param string|MetadataConstraintInterface $name
	 * @param mixed $value
	 * @param string|int $match
	 *
	 * @return $this
	 */
	public function withMetadata($name, $value = null, $match = 'any')
	{
		if ($name instanceof MetadataConstraintInterface)
		{
			$this->metadataConstraints[] = $name;
		}
		else
		{
			$this->metadataConstraints[] = new MetadataConstraint($name, $value, $match);
		}

		return $this;
	}

	/**
	 * @see static::getTypeMetadataConstraints() for getting type-specific metadata constraints
	 *
	 * @return list<MetadataConstraint>
	 */
	public function getMetadataConstraints()
	{
		return array_values(array_filter(
			$this->metadataConstraints,
			function (MetadataConstraintInterface $constraint)
			{
				return $constraint instanceof MetadataConstraint;
			}
		));
	}

	/**
	 * @see static::getMetadataConstraints() for getting generic metadata constraints
	 *
	 * @return list<TypeMetadataConstraint>
	 */
	public function getTypeMetadataConstraints(): array
	{
		return array_values(array_filter(
			$this->metadataConstraints,
			function (MetadataConstraintInterface $constraint)
			{
				return $constraint instanceof TypeMetadataConstraint;
			}
		));
	}

	/**
	 * @param list<string> $contentTypes
	 * @param list<MetadataConstraintInterface> $constraints
	 *
	 * @return $this
	 */
	public function withPermissionConstraints(array $contentTypes, array $constraints)
	{
		if (!$contentTypes || !$constraints)
		{
			return $this;
		}

		$key = implode('-', $contentTypes);
		if (!isset($this->permissionConstraints[$key]))
		{
			$this->permissionConstraints[$key] = [
				'types' => $contentTypes,
				'constraints' => [],
			];
		}

		$this->permissionConstraints[$key]['constraints'] = array_merge(
			$this->permissionConstraints[$key]['constraints'],
			$constraints
		);

		return $this;
	}

	/**
	 * @return array<string, array{types: list<string>, constraints: list<MetadataConstraint>}>
	 */
	public function getPermissionConstraints()
	{
		$permissionConstraints = [];

		foreach ($this->permissionConstraints AS $key => $permissionConstraint)
		{
			$permissionConstraint['constraints'] = array_values(array_filter(
				$permissionConstraint['constraints'],
				function (MetadataConstraintInterface $constraint)
				{
					return $constraint instanceof MetadataConstraint;
				}
			));

			$permissionConstraints[$key] = $permissionConstraint;
		}

		return $permissionConstraints;
	}

	/**
	 * @return array<string, array{types: list<string>, constraints: list<TypeMetadataConstraint>}>
	 */
	public function getPermissionTypeConstraints(): array
	{
		$permissionConstraints = [];

		foreach ($this->permissionConstraints AS $key => $permissionConstraint)
		{
			$permissionConstraint['constraints'] = array_values(array_filter(
				$permissionConstraint['constraints'],
				function (MetadataConstraintInterface $constraint)
				{
					return $constraint instanceof TypeMetadataConstraint;
				}
			));

			$permissionConstraints[$key] = $permissionConstraint;
		}

		return $permissionConstraints;
	}

	/**
	 * @return $this
	 */
	public function withSql(SqlConstraint $constraint)
	{
		$this->sqlConstraints[] = $constraint;

		return $this;
	}

	/**
	 * @return list<SqlConstraint>
	 */
	public function getSqlConstraints()
	{
		return $this->sqlConstraints;
	}

	/**
	 * @return bool
	 */
	public function hasQueryConstraints()
	{
		return ($this->sqlConstraints || $this->order instanceof SqlOrder);
	}

	public function withGroupedResults()
	{
		if ($this->handler)
		{
			$type = $this->handler->getGroupByType();
			if ($type)
			{
				$this->groupByType = $type;
			}
		}
	}

	/**
	 * @return string|null
	 */
	public function getGroupByType()
	{
		return $this->groupByType;
	}

	/**
	 * @param string|SqlOrder $order
	 *
	 * @return $this
	 */
	public function orderedBy($order)
	{
		if (is_string($order))
		{
			$this->orderName = $order;
		}

		if (is_string($order) && $this->handler)
		{
			$newOrder = $this->handler->getTypeOrder($order);
			if ($newOrder)
			{
				$order = $newOrder;
			}
		}

		$this->order = $order;

		return $this;
	}

	/**
	 * @return string|SqlOrder
	 */
	public function getOrder()
	{
		return $this->order;
	}

	/**
	 * @return string
	 */
	public function getOrderName()
	{
		return $this->orderName;
	}

	/**
	 * @param string $key
	 * @param string $message
	 *
	 * @return $this
	 */
	public function error($key, $message)
	{
		$this->errors[$key] = $message;

		return $this;
	}

	/**
	 * @return array<string, string>
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * @param string $key
	 * @param string $message
	 *
	 * @return $this
	 */
	public function warning($key, $message)
	{
		$this->warnings[$key] = $message;

		return $this;
	}

	/**
	 * @return array<string, string>
	 */
	public function getWarnings()
	{
		return $this->warnings;
	}

	/**
	 * @return string
	 */
	public function getUniqueQueryHash()
	{
		return md5(serialize(array_merge(
			$this->getGlobalUniqueQueryComponents(),
			$this->getUniqueQueryComponents()
		)));
	}

	/**
	 * @return array{
	 *     handlerType: string|null,
	 *     types: list<string>,
	 *     userIds: list<int>,
	 *     maxDate: int,
	 *     minDate: int,
	 *     metadataConstraints: list<MetadataConstraintInterface>,
	 *     sqlConstraints: list<SqlConstraint>,
	 *     groupByType: string|null,
	 *     order: string|SqlOrder,
	 * }
	 */
	public function getGlobalUniqueQueryComponents()
	{
		return [
			'handlerType' => $this->handlerType,
			'types' => $this->types,
			'userIds' => $this->userIds,
			'maxDate' => $this->maxDate,
			'minDate' => $this->minDate,
			'metadataConstraints' => $this->metadataConstraints,
			'sqlConstraints' => $this->sqlConstraints,
			'groupByType' => $this->groupByType,
			'order' => $this->order,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getUniqueQueryComponents()
	{
		return [];
	}
}
