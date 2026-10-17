<?php

namespace XF\ControllerPlugin;

use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;
use XF\Repository\FindNewDefaultRepository;
use XF\Repository\FindNewRepository;

use function is_array;

class FindNewPlugin extends AbstractPlugin
{
	public function getRequestedFilters(AbstractHandler $handler)
	{
		$filters = $handler->getFiltersFromInput($this->request);
		if (!$filters)
		{
			// Skip user or type defaults; the filters from the input are explicit.
			// This should be set when submitting the filter form or if you need to link into the system
			// and want no filters guaranteed.
			$skip = $this->filter('skip', 'bool');
			if ($skip)
			{
				$filters = [];
			}
			else
			{
				$filters = $this->getFallbackFilters($handler);
			}
		}

		return $filters;
	}

	public function getFallbackFilters(AbstractHandler $handler)
	{
		$filters = null;

		$userId = \XF::visitor()->user_id;
		if ($userId)
		{
			$filters = $this->getFindNewDefaultRepo()->getUserDefaultFilters($userId, $handler->getContentType());
		}

		if (!is_array($filters))
		{
			$filters = $handler->getDefaultFilters();
		}

		return $filters;
	}

	public function runFindNewSearch(AbstractHandler $handler, array $filters)
	{
		$findNew = $this->em()->create(FindNew::class);
		$findNew->content_type = $handler->getContentType();
		$findNew->user_id = \XF::visitor()->user_id;
		$findNew->filters = $filters;

		$cacheLength = $findNew->user_id ? 5 : 45; // Increase cache from 5 seconds to 45 seconds for guests

		$cached = $this->getFindNewRepo()->getAvailableCachedFindNewRecord($findNew, $cacheLength);
		if ($cached)
		{
			return $cached;
		}

		$maxResults = $this->options()->maximumSearchResults;
		$findNew->results = $handler->getResultIds($filters, $maxResults);

		return $findNew;
	}

	public function findNewRequiresSaving(FindNew $findNew)
	{
		return (!$findNew->exists() && ($findNew->results || $findNew->filters));
	}

	public function saveDefaultFilters(AbstractHandler $handler, array $filters)
	{
		$this->getFindNewDefaultRepo()->saveUserDefaultFilters(
			\XF::visitor()->user_id,
			$handler->getContentType(),
			$filters
		);
	}

	/**
	 * @param string $contentType;
	 *
	 * @return AbstractHandler|null
	 */
	public function getFindNewHandler($contentType)
	{
		$handler = $this->getFindNewRepo()->getFindNewHandler($contentType);
		if ($handler && $handler->isAvailable())
		{
			return $handler;
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param integer $findNewId
	 * @param string $expectedContentType
	 *
	 * @return FindNew|null
	 */
	public function getFindNewRecord($findNewId, $expectedContentType)
	{
		if (!$findNewId)
		{
			return null;
		}

		$findNew = $this->em()->find(FindNew::class, $findNewId);

		if (
			$findNew
			&& $findNew->content_type === $expectedContentType
			&& $findNew->user_id == \XF::visitor()->user_id
		)
		{
			return $findNew;
		}
		else
		{
			return null;
		}
	}

	/**
	 * @return FindNewRepository
	 */
	protected function getFindNewRepo()
	{
		return $this->repository(FindNewRepository::class);
	}

	/**
	 * @return FindNewDefaultRepository
	 */
	protected function getFindNewDefaultRepo()
	{
		return $this->repository(FindNewDefaultRepository::class);
	}
}
