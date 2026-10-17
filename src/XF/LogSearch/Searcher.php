<?php

namespace XF\LogSearch;

use XF\App;
use XF\Mvc\Entity\AbstractCollection;

use function array_key_exists, count, in_array;

class Searcher
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var AbstractHandler[]
	 */
	protected $handlers = [];

	protected $typeLimit = 5;

	public function __construct(App $app, array $handlerClasses = [])
	{
		$this->app = $app;

		$this->addHandlerClasses($handlerClasses);
	}

	public function getSearcherNamesForList()
	{
		$phrases = $this->app->getContentTypePhrases(true);

		$list = [];

		foreach ($this->handlers AS $contentType => $handler)
		{
			if (array_key_exists($contentType, $phrases))
			{
				$list[$contentType] = $phrases[$contentType];
			}
		}

		return $list;
	}

	/**
	 * @param string $text
	 *
	 * @return TypeResultSet[]
	 */
	public function search($text, array $contentTypes = [], $start = 0, $end = 0)
	{
		if (!$text)
		{
			return [];
		}

		$displayOrder = [];

		$resultTypes = [];
		foreach ($this->handlers AS $contentType => $handler)
		{
			if (!empty($contentTypes) && !in_array($contentType, $contentTypes))
			{
				continue;
			}

			if (!$handler->isSearchable())
			{
				continue;
			}

			$handler->applyDateConstraints($start, $end);

			$maxResults = $handler->getMaxTypeResults($this->typeLimit);

			$results = $handler->search($text, $maxResults);
			if ($results && count($results))
			{
				if ($results instanceof AbstractCollection)
				{
					$results = $results->toArray();
				}

				$displayOrder[$contentType] = $handler->getDisplayOrder();
				$resultTypes[$contentType] = new TypeResultSet($contentType, $handler, $results);
			}
		}

		asort($displayOrder);

		$output = [];
		foreach (array_keys($displayOrder) AS $contentType)
		{
			$output[$contentType] = $resultTypes[$contentType];
		}

		return $output;
	}

	public function addHandler($contentType, AbstractHandler $handler)
	{
		$this->handlers[$contentType] = $handler;
	}

	public function addHandlerClass($contentType, $handlerClass)
	{
		if (!class_exists($handlerClass))
		{
			return false;
		}

		$class = \XF::extendClass($handlerClass);
		$this->handlers[$contentType] = new $class($contentType, $this->app);
		return true;
	}

	public function addHandlerClasses(array $classes)
	{
		$output = [];
		foreach ($classes AS $contentType => $class)
		{
			$output[$contentType] = $this->addHandlerClass($contentType, $class);
		}

		return $output;
	}
}
