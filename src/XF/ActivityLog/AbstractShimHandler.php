<?php

namespace XF\ActivityLog;

use XF\Mvc\Entity\Entity;

/**
 * This is an activity handler with all public methods shimmed, primarily used
 * for proxying activity log records to another entity.
 *
 * @template T of Entity
 *
 * @extends AbstractHandler<T>
 */
abstract class AbstractShimHandler extends AbstractHandler
{
	public function log(
		Entity $content,
		int $logDate,
		array $values,
		bool $increment
	): void
	{
		return;
	}

	public function bulkLog(array $values, bool $increment): void
	{
		return;
	}

	public function updateContainerId(Entity $content, bool $force = false): void
	{
		return;
	}

	public function mergeLogs(
		Entity $target,
		array $sourceIds,
		?array $metrics = null
	): void
	{
		return;
	}

	public function removeLogs(Entity $content): void
	{
		return;
	}

	public function rebuildReplyMetrics(Entity $content): void
	{
		return;
	}

	public function rebuildReactionMetrics(Entity $content): void
	{
		return;
	}

	public function rebuildVoteMetrics(Entity $content): void
	{
		return;
	}
}
