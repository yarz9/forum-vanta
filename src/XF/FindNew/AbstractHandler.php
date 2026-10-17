<?php

namespace XF\FindNew;

use XF\Entity\FindNew;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\AbstractReply;

/**
 * @template T of \XF\Mvc\Entity\Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @return string
	 */
	abstract public function getRoute();

	/**
	 * @param array<int, T> $results
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return AbstractReply
	 */
	abstract public function getPageReply(
		Controller $controller,
		FindNew $findNew,
		array $results,
		$page,
		$perPage
	);

	/**
	 * @return array<string, mixed>
	 */
	abstract public function getFiltersFromInput(Request $request);

	/**
	 * @return array<string, mixed>
	 */
	abstract public function getDefaultFilters();

	/**
	 * @param array<string, mixed> $filters
	 * @param int $maxResults
	 *
	 * @return list<int>
	 */
	abstract public function getResultIds(array $filters, $maxResults);

	/**
	 * @param list<int> $ids
	 *
	 * @return AbstractCollection<T>
	 */
	abstract public function getPageResultsEntities(array $ids);

	/**
	 * @return int
	 */
	abstract public function getResultsPerPage();

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @return bool
	 */
	public function isAvailable()
	{
		return true;
	}

	/**
	 * @param list<int> $ids
	 *
	 * @return AbstractCollection<T>
	 */
	public function getPageResults(array $ids)
	{
		$results = $this->getPageResultsEntities($ids);
		$results = $this->filterResults($results);
		return $results->sortByList($ids);
	}

	/**
	 * @param AbstractCollection<T> $results
	 *
	 * @return AbstractCollection<T>
	 */
	protected function filterResults(AbstractCollection $results)
	{
		return $results->filterViewable();
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}
}
