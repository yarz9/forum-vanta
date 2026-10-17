<?php

namespace XF\AdminSearch;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Router;

/**
 * Class AbstractFieldSearch
 *
 * Note, this assumes that the Finder named in getFinderName() correlates to an entity of the same name.
 * If this is not the case, override getEntityName(),
 * or if the entity defines a complex primary key, override getContentIdName()
 *
 * @package XF\AdminSearch
 */
abstract class AbstractFieldSearch extends AbstractHandler
{
	public const NO_SPACES = '/^[^\s]+$/';

	/** @var null|Finder  */
	protected $finder = null;

	abstract protected function getFinderName();
	abstract protected function getRouteName();

	/**
	 * @return Finder|null
	 */
	protected function getFinder()
	{
		if ($this->finder instanceof Finder)
		{
			return $this->finder;
		}

		$finderName = $this->getFinderName();

		return $this->app->finder($finderName);
	}

	/**
	 * @return string
	 */
	protected function getEntityName()
	{
		return $this->getFinderName();
	}

	/**
	 * @return string
	 */
	protected function getContentIdName()
	{
		$entityName = $this->getEntityName();

		return $this->app->em()->getEntityStructure($entityName);
	}

	/**
	 * @var array Fields to be searched for $text. The first field here is assumed to be the title field.
	 */
	protected $searchFields = ['title'];

	/**
	 * Use this to set any default conditions on the search finder, such as visible=1 etc.
	 *
	 * @param Finder $finder
	 */
	protected function getFinderConditions(Finder &$finder)
	{
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		$finder = $this->getFinder();
		$this->getFinderConditions($finder);

		$conditions = [];
		$escapedLike = $finder->escapeLike($text, '%?%');

		foreach ($this->searchFields AS $index => $searchField)
		{
			if (!is_numeric($index))
			{
				// in this instance, $searchField is a regex
				if (!preg_match($searchField, $text))
				{
					// didn't match, so don't bother searching the DB for this field
					continue;
				}

				// put the actual search text into place for the DB search
				$searchField = $index;
			}

			$conditions[] = [$searchField, 'like', $escapedLike];
		}

		if (empty($conditions))
		{
			return false;
		}

		$conditions = $this->getConditions($conditions, $text, $escapedLike);

		if ($previousMatchIds)
		{
			$conditions[] = [$this->getContentIdName(), $previousMatchIds];
		}

		if (isset($this->searchFields[0]))
		{
			$order = $this->searchFields[0];
		}
		else if (function_exists('array_key_first'))
		{
			$order = array_key_first($this->searchFields);
		}
		else
		{
			$order = $this->arrayKeyFirst($this->searchFields);
		}

		$finder
			->whereOr($conditions)
			->setDefaultOrder($order)
			->limit($limit);

		return $finder->fetch();
	}

	protected function getConditions(array $conditions, $text, $escapedLike)
	{
		return $conditions;
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return $this->getTemplateParams($router, $record, [
			'link' => $router->buildLink($this->getRouteName(), $record),
			'title' => $record->{$this->searchFields[0]},
		]);
	}

	/**
	 * @param Router $router
	 * @param Entity $record
	 * @param array  $templateParams
	 *
	 * @return array
	 */
	protected function getTemplateParams(Router $router, Entity $record, array $templateParams)
	{
		return $templateParams;
	}

	protected function arrayKeyFirst(array $arr)
	{
		foreach ($arr AS $key => $unused)
		{
			return $key;
		}
		return null;
	}
}
