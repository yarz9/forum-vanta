<?php

namespace XF\Mvc\Entity;

/**
 * @template T of \XF\Mvc\Entity\Entity
 * @extends AbstractCollection<T>
 */
class ArrayCollection extends AbstractCollection
{
	/**
	 * @param array<int|string,T> $entities
	 */
	public function __construct(array $entities)
	{
		$this->entities = $entities;
		$this->populated = true;
	}

	protected function populateInternal()
	{
	}
}
