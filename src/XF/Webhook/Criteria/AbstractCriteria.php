<?php

namespace XF\Webhook\Criteria;

use XF\App;
use XF\Util\Arr;
use XF\Util\Php;
use XF\Util\Str;

use function is_array;

abstract class AbstractCriteria
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var array $criteria
	 */
	protected $criteria = [];

	/**
	 * @var bool
	 */
	protected $matchOnEmpty = true;

	public function __construct(App $app, array $criteria, string $contentType)
	{
		$this->app = $app;
		$this->contentType = $contentType;
		$this->setCriteria($criteria);
	}

	public function isMatched(array $data): bool
	{
		if (!$this->criteria)
		{
			return $this->matchOnEmpty;
		}

		foreach ($this->criteria AS $criterion)
		{
			$rule = $criterion['rule'];
			$criteria = $criterion['data'];

			$specialResult = $this->isSpecialMatched($rule, $criteria, $data);
			if ($specialResult === false)
			{
				return false;
			}

			if ($specialResult === true)
			{
				continue;
			}

			$method = '_match' . Php::camelCase($rule);
			if (method_exists($this, $method))
			{
				$result = $this->$method($criteria, $data);
				if (!$result)
				{
					return false;
				}
			}
			else if (!$this->isUnknownMatched($rule, $criteria, $data))
			{
				return false;
			}
		}

		return true;
	}

	protected function isSpecialMatched(string $rule, array $criteria, array $data): ?bool
	{
		return null;
	}

	protected function isUnknownMatched(string $rule, array $criteria, array $data): bool
	{
		$eventReturnValue = false;
		$this->app->fire('criteria_webhook', [$rule, $criteria, $data, $this->contentType, &$eventReturnValue]);

		return $eventReturnValue;
	}

	public function setContentType(string $contentType): void
	{
		$this->contentType = $contentType;
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}

	public function setCriteria(array $criteria): void
	{
		$this->criteria = $this->filterCriteria($criteria);
	}

	public function getCriteria(): array
	{
		return $this->criteria;
	}

	public function getCriteriaForTemplate(?string $contentType = null): array
	{
		$output = [];
		foreach ($this->criteria AS $criterion)
		{
			$data = (!empty($criterion[$contentType]['data']) ? $criterion[$contentType]['data'] : true);
			$output[$criterion[$contentType]['rule']] = $data;
		}

		return $output;
	}

	protected function filterCriteria(array $criteria): array
	{
		$criteriaFiltered = [];
		foreach ($criteria AS $criterion)
		{
			if (!empty($criterion['rule']))
			{
				if (empty($criterion['data']) || !is_array($criterion['data']))
				{
					$criterion['data'] = [];
				}
				$criteriaFiltered[] = [
					'rule' => $criterion['rule'],
					'data' => $criterion['data'],
				];
			}
		}

		return $criteriaFiltered;
	}

	protected function findNeedle($needleList, $haystack)
	{
		$haystack = Str::strtolower($haystack);

		foreach (Arr::stringToArray(Str::strtolower($needleList), '/\s*,\s*/') AS $needle)
		{
			if (strpos($haystack, $needle) !== false)
			{
				return $needle;
			}
		}

		return false;
	}
}
