<?php

namespace XF\Filterer;

use XF\Entity\User;
use XF\Finder\PhraseMapFinder;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\Finder;

use function in_array;

class Attachment extends AbstractFilterer
{
	protected $defaultOrder = 'attach_date';
	protected $defaultDirection = 'desc';

	protected $validSorts = [
		'attach_date' => true,
		'file_size' => 'Data.file_size',
	];

	protected function getFinderType(): string
	{
		return 'XF:Attachment';
	}

	protected function initFinder(Finder $finder, array $setupData)
	{
		$finder
			->with('Data', true)
			->with('Data.User')
			->setDefaultOrder($this->defaultOrder, $this->defaultDirection);
	}

	protected function getFilterTypeMap(): array
	{
		return [
			'content_type' => 'str',
			'username' => 'str',
			'start' => 'datetime',
			'end' => 'datetime',
			'order' => 'str',
			'direction' => 'str',
		];
	}

	protected function getLookupTypeList(): array
	{
		return [
			'order',
		];
	}

	protected function onFinalize()
	{
		$finder = $this->finder;

		$sorts = $this->validSorts;
		$order = $this->rawFilters['order'] ?? null;
		$direction = $this->rawFilters['direction'] ?? null;

		if ($order && isset($sorts[$order]))
		{
			if (!in_array($direction, ['asc', 'desc']))
			{
				$direction = 'desc';
			}

			$defaultOrder = $this->defaultOrder;
			$defaultDirection = $this->defaultDirection;

			if ($order != $defaultOrder || $direction != $defaultDirection)
			{
				if ($sorts[$order] === true)
				{
					$finder->order($order, $direction);
				}
				else
				{
					$finder->order($sorts[$order], $direction);
				}

				$this->addLinkParam('order', $order);
				$this->addLinkParam('direction', $direction);
				$this->addDisplayValue('order', $order . '_' . $direction);
			}
		}
	}

	protected function applyFilter(string $filterName, &$value, &$displayValue): bool
	{
		/** @var PhraseMapFinder $finder */
		$finder = $this->finder;

		switch ($filterName)
		{
			case 'content_type':
				if (!$value)
				{
					return false;
				}

				$displayValue = $this->app()->getContentTypePhrase($value);
				$finder->where('content_type', $value);
				return true;

			case 'username':
				/** @var User $user */
				$user = $this->app()->finder(UserFinder::class)->where('username', $value)->fetchOne();
				if (!$user)
				{
					return false;
				}
				$finder->where('Data.user_id', $user->user_id);
				return true;

			case 'start':
			case 'end':
				if (!$value)
				{
					return false;
				}

				$lookup = [
					'start' => ['>=', 'attach_date'],
					'end' => ['<', 'attach_date'],
				];

				$finder->where($lookup[$filterName][1], $lookup[$filterName][0], $value);
				$displayValue = \XF::language()->date($value);
				return true;
		}

		return false;
	}
}
