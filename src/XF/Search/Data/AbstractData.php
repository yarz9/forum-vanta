<?php

namespace XF\Search\Data;

use XF\Http\Request;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\TypeMetadataConstraint;
use XF\Search\Search;

use function count, is_array;

/**
 * @template T of Entity
 */
abstract class AbstractData
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var Search
	 */
	protected $searcher;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType, Search $searcher)
	{
		$this->contentType = $contentType;
		$this->searcher = $searcher;
	}

	/**
	 * @param T $entity
	 *
	 * @return IndexRecord|null
	 */
	abstract public function getIndexData(Entity $entity);

	abstract public function setupMetadataStructure(MetadataStructure $structure);

	/**
	 * @param T $entity
	 *
	 * @return int
	 */
	abstract public function getResultDate(Entity $entity);

	/**
	 * @param T $entity
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, mixed>
	 */
	abstract public function getTemplateData(Entity $entity, array $options = []);

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function getMetadataStructure()
	{
		$structure = new MetadataStructure();
		$this->setupMetadataStructure($structure);

		return $structure->getFields();
	}

	/**
	 * @param bool $forView
	 *
	 * @return list<string>
	 */
	public function getEntityWith($forView = false)
	{
		return [];
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return 'public:search_result_' . $this->contentType;
	}

	/**
	 * @return string
	 */
	public function renderResult(Entity $entity, array $options = [])
	{
		$template = $this->getTemplateName();
		$data = $this->getTemplateData($entity, $options);

		return \XF::app()->templater()->renderTemplate($template, $data);
	}

	/**
	 * @return list<string>
	 */
	public function getSearchableContentTypes()
	{
		return [$this->contentType];
	}

	/**
	 * @return array{title: string, order?: int}|null
	 */
	public function getSearchFormTab()
	{
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getSectionContext()
	{
		return null;
	}

	/**
	 * @return string
	 */
	public function getTypeFormTemplate()
	{
		return 'public:search_form_' . $this->contentType;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getSearchFormData()
	{
		return [];
	}

	/**
	 * @param array<string, mixed> $urlConstraints
	 */
	public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints)
	{
	}

	/**
	 * @return string|null
	 */
	public function getGroupByType()
	{
		return null;
	}

	/**
	 * @param string|null $order
	 *
	 * @return mixed
	 */
	public function getTypeOrder($order)
	{
		return null;
	}

	/**
	 * This allows you to specify constraints to avoid including search results
	 * that will ultimately be filtered out due to permissions. In most cases,
	 * the query should not be modified. It is passed in to allow inspection.
	 *
	 * Note that the returned constraints may apply to other content types, so
	 * you may need to vary the constraints based on `$isOnlyType`. When
	 * `$isOnlyType` is `false`, you should return only "none" constraints on
	 * keys that are unique to the pertinent content types.
	 *
	 * @see static::getTypePermissionTypeConstraints() for applying constraints to only certain types
	 *
	 * @param bool $isOnlyType True if the search is limited to only this type
	 *
	 * @return list<MetadataConstraint>
	 */
	public function getTypePermissionConstraints(Query $query, $isOnlyType)
	{
		return [];
	}

	/**
	 * This allows you to specify type constraints to avoid including search
	 * results that will ultimately be filtered out due to permissions. In most
	 * cases, the query should not be modified. It is passed in to allow
	 * inspection. Generic constraints should be favored instead, where possible.
	 *
	 * Note that the returned constraints will never apply to other content
	 * types.  You may use them to apply sub-constraints to specific types, or
	 * filter out types entirely. When `$isOnlyType` is `true`, you can likely
	 * use generic constraints instead.
	 *
	 * @see static::getTypePermissionConstraints() for applying generic constraints
	 *
	 * @param bool $isOnlyType True if the search is limited to only this type
	 *
	 * @return list<TypeMetadataConstraint>
	 */
	public function getTypePermissionTypeConstraints(
		Query $query,
		bool $isOnlyType
	): array
	{
		return [];
	}

	/**
	 * @param T $entity
	 * @param string|null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return false;
	}

	/**
	 * @param T $entity
	 * @param string|null $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $entity, &$error = null)
	{
		if (method_exists($entity, 'canView'))
		{
			return $entity->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	/**
	 * @param T $entity
	 * @param array<string, array{string, int}> $resultIds
	 *
	 * @return bool
	 */
	public function canIncludeInResults(Entity $entity, array $resultIds)
	{
		return true;
	}

	/**
	 * @param int|list<int> $id
	 * @param bool $forView
	 *
	 * @return AbstractCollection<T>|T|null
	 */
	public function getContent($id, $forView = false)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith($forView));
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 * @param bool $forView
	 *
	 * @return AbstractCollection<T>
	 */
	public function getContentInRange($lastId, $amount, $forView = false)
	{
		$entityId = \XF::app()->getContentTypeFieldValue($this->contentType, 'entity');
		if (!$entityId)
		{
			throw new \LogicException("Content type {$this->contentType} must define an 'entity' value");
		}

		$em = \XF::em();
		$key = $em->getEntityStructure($entityId)->primaryKey;
		if (is_array($key))
		{
			if (count($key) > 1)
			{
				throw new \LogicException("Entity $entityId must only have a single primary key");
			}
			$key = reset($key);
		}

		$finder = $em->getFinder($entityId)->where($key, '>', $lastId)
			->order($key)
			->with($this->getEntityWith($forView));

		return $finder->fetch($amount);
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}
}
