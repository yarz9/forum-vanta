<?php

namespace XF\Search\Data;

use XF\Mvc\Entity\Entity;

/**
 * @template T of Entity
 */
interface AutoCompletableInterface
{
	/**
	 * @param T $entity
	 * @param array<string, mixed> $options
	 *
	 * @return array{
	 *     text: string,
	 *     url: string,
	 *     desc?: string,
	 *     icon?: string,
	 *     iconHtml?: string,
	 * }|null
	 */
	public function getAutoCompleteResult(
		Entity $entity,
		array $options = []
	): ?array;
}
