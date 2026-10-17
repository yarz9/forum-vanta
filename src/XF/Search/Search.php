<?php

namespace XF\Search;

use XF\Mvc\Entity\Entity;
use XF\ResultSet;
use XF\ResultSetInterface;
use XF\Search\Data\AbstractData;
use XF\Search\Data\AutoCompletableInterface;
use XF\Search\Query\KeywordQuery;
use XF\Search\Query\Query;
use XF\Search\Source\AbstractSource;
use XF\Util\Arr;

use function count, intval, is_array, is_int;

class Search implements ResultSetInterface
{
	/**
	 * @var AbstractSource
	 */
	protected $source;

	/**
	 * @var array<string, class-string<Data\AbstractData>>
	 */
	protected $types;

	/**
	 * @var array<string, AbstractData>
	 */
	protected $handlers = [];

	/**
	 * @param array<string, class-string<Data\AbstractData>> $types
	 */
	public function __construct(AbstractSource $source, array $types)
	{
		$this->source = $source;
		$this->types = $types;
	}

	/**
	 * @param string $contentType
	 * @param Entity|int $entity
	 * @param bool $deleteIfNeeded
	 *
	 * @return bool
	 */
	public function index($contentType, $entity, $deleteIfNeeded = true)
	{
		$handler = $this->handler($contentType);

		if (!$entity instanceof Entity)
		{
			$entity = $handler->getContent(intval($entity));
			if (!$entity)
			{
				return false;
			}
		}

		$record = $handler->getIndexData($entity);
		if ($record)
		{
			$this->source->index($record);
			return true;
		}
		else
		{
			if ($deleteIfNeeded)
			{
				$this->delete($contentType, $entity);
			}
			return false;
		}
	}

	/**
	 * @param string $contentType
	 * @param list<Entity|int> $entities
	 */
	public function indexEntities($contentType, $entities)
	{
		$this->enableBulkIndexing();

		foreach ($entities AS $entity)
		{
			$this->index($contentType, $entity);
		}

		$this->disableBulkIndexing();
	}

	/**
	 * @param string $contentType
	 * @param list<int> $contentIds
	 */
	public function indexByIds($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return;
		}

		$entities = $this->handler($contentType)->getContent($contentIds);
		$this->indexEntities($contentType, $entities);
	}

	/**
	 * @param string $contentType
	 * @param int $lastId
	 * @param int $amount
	 *
	 * @return int|false
	 */
	public function indexRange($contentType, $lastId, $amount)
	{
		$handler = $this->handler($contentType);
		$entities = $handler->getContentInRange($lastId, $amount);
		if (!$entities->count())
		{
			return false;
		}

		$this->indexEntities($contentType, $entities);

		$keys = $entities->keys();
		return $keys ? max($keys) : false;
	}

	public function enableBulkIndexing()
	{
		$this->source->enableBulkIndexing();
	}

	public function disableBulkIndexing()
	{
		$this->source->disableBulkIndexing();
	}

	/**
	 * @param string $contentType
	 * @param list<int>|int|Entity $del
	 */
	public function delete($contentType, $del)
	{
		if ($del instanceof Entity)
		{
			$del = $del->getIdentifierValues();
			if (!$del || count($del) != 1)
			{
				throw new \InvalidArgumentException("Entity does not have an ID or does not have a simple key");
			}
			$del = intval(reset($del));
		}

		if (!is_int($del) && !is_array($del))
		{
			throw new \InvalidArgumentException("IDs to delete must be an array or an integer");
		}
		if (!$del)
		{
			return;
		}

		$this->source->delete($contentType, $del);
	}

	/**
	 * @param string|null $type
	 */
	public function truncate($type = null)
	{
		return $this->source->truncate($type);
	}

	/**
	 * @param int $oldUserId
	 * @param int $newUserId
	 */
	public function reassignContent($oldUserId, $newUserId)
	{
		$this->source->reassignContent($oldUserId, $newUserId);
	}

	/**
	 * @return KeywordQuery
	 */
	public function getQuery()
	{
		$extendClass = \XF::extendClass(KeywordQuery::class);
		return new $extendClass($this);
	}

	/**
	 * @param string|null $error
	 *
	 * @return bool
	 */
	public function isQueryEmpty(Query $query, &$error = null)
	{
		return $this->source->isQueryEmpty($query, $error);
	}

	/**
	 * @param string $keywords
	 * @param string|null $error
	 * @param string|null $warning
	 *
	 * @return string
	 */
	public function getParsedKeywords($keywords, &$error = null, &$warning = null)
	{
		return $this->source->parseKeywords($keywords, $error, $warning);
	}

	public function isAutoCompleteSupported(): bool
	{
		return $this->source->isAutoCompleteSupported();
	}

	/**
	 * @return bool
	 */
	public function isRelevanceSupported()
	{
		return $this->source->isRelevanceSupported();
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public function autoComplete(
		KeywordQuery $query,
		?int $maxResults = null,
		bool $applyVisitorPermissions = true
	): array
	{
		if (!$this->isAutoCompleteSupported())
		{
			return [];
		}

		$types = $query->getTypes();
		if ($types)
		{
			$autoCompletableTypes = $this->getAutoCompletableTypes();
			if (!array_intersect($types, $autoCompletableTypes))
			{
				return [];
			}
		}

		return $this->executeSearch(
			$query,
			$maxResults,
			function ($query, $maxResults)
			{
				return $this->source->autoComplete($query, $maxResults);
			},
			$applyVisitorPermissions
		);
	}


	/**
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, array{
	 *     id: string,
	 *     type: string,
	 *     text: string,
	 *     url: string,
	 *     desc?: string,
	 *     icon?: string,
	 *     iconHtml?: string,
	 * }>
	 */
	public function getAutoCompleteResults(
		ResultSet $resultSet,
		array $options = []
	): array
	{
		return $resultSet->getResultsDataCallback(
			function (Entity $result, string $type) use ($options)
			{
				$handler = $this->handler($type);
				if (!($handler instanceof AutoCompletableInterface))
				{
					return null;
				}

				$autoCompleteResult = $handler->getAutoCompleteResult(
					$result,
					$options
				);
				if ($autoCompleteResult === null)
				{
					return null;
				}

				return array_merge(
					[
						'id' => $result->getEntityContentTypeId(),
						'type' => \XF::app()->getContentTypePhrase($type),
					],
					$autoCompleteResult
				);
			}
		);
	}

	/**
	 * @return list<string>
	 */
	public function getAutoCompletableTypes(): array
	{
		return array_keys(array_filter(
			$this->getValidHandlers(),
			function (AbstractData $handler): bool
			{
				return $handler instanceof AutoCompletableInterface;
			}
		));
	}

	/**
	 * @param KeywordQuery $query
	 * @param int|null $maxResults
	 * @param bool $applyVisitorPermissions
	 *
	 * @return array<string, array{string, int}>
	 */
	public function search(
		KeywordQuery $query,
		$maxResults = null,
		$applyVisitorPermissions = true
	)
	{
		return $this->executeSearch(
			$query,
			$maxResults,
			function ($query, $maxResults)
			{
				return $this->source->search($query, $maxResults);
			},
			$applyVisitorPermissions
		);
	}

	/**
	 * @param Query $query
	 * @param int|null $maxResults
	 * @param \Closure(Query $query, int $maxResults): list<array{content_type: string, content_result:int}> $resultBuilder
	 * @param bool $applyVisitorPermissions
	 *
	 * @return array<string, array{string, int}>
	 */
	protected function executeSearch(
		Query $query,
		$maxResults,
		\Closure $resultBuilder,
		$applyVisitorPermissions = true
	)
	{
		$maxResults = intval($maxResults);
		if ($maxResults <= 0)
		{
			$maxResults = max(\XF::options()->maximumSearchResults, 20);
		}

		$this->applyPermissionConstraints($query);

		$results = $resultBuilder($query, $maxResults);

		$resultSet = $this->getResultSet($results)->limitResults($maxResults, $applyVisitorPermissions);
		return $resultSet->getResults();
	}

	protected function applyPermissionConstraints(Query $query)
	{
		$this->applyGlobalPermissionConstraints($query);

		$handler = $query->getHandler();
		if ($handler)
		{
			// we're already restricted to the correct content types, so we can skip the permission constraint approach
			foreach ($handler->getTypePermissionConstraints($query, true) AS $constraint)
			{
				$query->withMetadata($constraint);
			}

			foreach ($handler->getTypePermissionTypeConstraints($query, true) AS $constraint)
			{
				$query->withMetadata($constraint);
			}
		}
		else
		{
			foreach ($this->getValidHandlers() AS $handler)
			{
				$query->withPermissionConstraints(
					$handler->getSearchableContentTypes(),
					$handler->getTypePermissionConstraints($query, false)
				);

				$query->withPermissionConstraints(
					$handler->getSearchableContentTypes(),
					$handler->getTypePermissionTypeConstraints($query, false)
				);
			}
		}
	}

	protected function applyGlobalPermissionConstraints(Query $query)
	{
		if (\XF::visitor()->is_moderator && $query->getAllowHidden() === null)
		{
			$query->allowHidden();
		}
	}

	/**
	 * @param list<array{content_type: string, content_id: int}>|list<array{string, int}>|list<string> $results
	 *
	 * @return ResultSet
	 */
	public function getResultSet(array $results)
	{
		return new ResultSet($this, $results);
	}

	public function getResultSetData($type, array $ids, $filterViewable = true, ?array $results = null)
	{
		if (!$this->isValidContentType($type))
		{
			return [];
		}

		$handler = $this->handler($type);
		$entities = $handler->getContent($ids, true);

		if ($filterViewable)
		{
			$entities = $entities->filter(function ($entity) use ($handler)
			{
				return $handler->canViewContent($entity);
			});
		}

		if (is_array($results))
		{
			$entities = $entities->filter(function ($entity) use ($handler, $results)
			{
				return $handler->canIncludeInResults($entity, $results);
			});
		}

		return $entities;
	}

	/**
	 * @param ResultSet $resultSet
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, RenderWrapper>
	 */
	public function wrapResultsForRender(ResultSet $resultSet, array $options = [])
	{
		return $resultSet->getResultsDataCallback(function ($result, $type, $id) use ($options)
		{
			return new RenderWrapper($this->handler($type), $result, $options);
		});
	}

	/**
	 * @return bool
	 */
	public function isValidContentType($type)
	{
		return isset($this->types[$type]) && class_exists($this->types[$type]);
	}

	/**
	 * @return list<string>
	 */
	public function getAvailableTypes()
	{
		return array_keys($this->types);
	}

	/**
	 * @param string $type
	 *
	 * @return AbstractData
	 */
	public function handler($type)
	{
		if (isset($this->handlers[$type]))
		{
			return $this->handlers[$type];
		}

		if (!isset($this->types[$type]))
		{
			throw new \InvalidArgumentException("Unknown search handler type '$type'");
		}

		$class = $this->types[$type];
		if (class_exists($class))
		{
			$class = \XF::extendClass($class);
		}

		$this->handlers[$type] = new $class($type, $this);
		return $this->handlers[$type];
	}

	/**
	 * @return array<string, AbstractData>
	 */
	public function getValidHandlers()
	{
		$handlers = [];
		foreach ($this->getAvailableTypes() AS $type)
		{
			if ($this->isValidContentType($type))
			{
				$handlers[$type] = $this->handler($type);
			}
		}

		return $handlers;
	}

	/**
	 * @return array<string, array{title: string, order: int}>
	 */
	public function getSearchTypeTabs()
	{
		$tabs = [];
		foreach ($this->getValidHandlers() AS $type => $handler)
		{
			$tab = $handler->getSearchFormTab();
			if ($tab)
			{
				if (!isset($tab['order']))
				{
					$tab['order'] = 10;
				}
				$tabs[$type] = $tab;
			}
		}

		$tabs = Arr::columnSort($tabs, 'order');

		return $tabs;
	}
}
