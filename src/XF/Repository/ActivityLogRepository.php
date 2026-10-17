<?php

namespace XF\Repository;

use XF\ActivityLog\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

class ActivityLogRepository extends Repository
{
	/**
	 * @var int
	 */
	public const MAX_RETENTION_DAYS = 365;

	/**
	 * Logs activity for the given content. The values should be a map of metrics
	 * to values.
	 *
	 * @param array<int, array<string, int>> $values
	 */
	public function log(
		Entity $content,
		int $logDate,
		array $values,
		bool $increment = true
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->log($content, $logDate, $values, $increment);
	}

	/**
	 * Logs multiple entries. The values should be a list of log entries,
	 * including a log date, content ID, content date, content container ID, and
	 * metrics.
	 *
	 * @param list<array<string, int>> $values
	 */
	public function bulkLog(
		string $contentType,
		array $values,
		bool $increment = true
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($contentType);
		if (!$handler)
		{
			return;
		}

		$handler->bulkLog($values, $increment);
	}

	/**
	 * Updates the container ID for the given content if it has changed.
	 */
	public function updateContainerId(Entity $content, bool $force = false): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->updateContainerId($content, $force);
	}

	/**
	 * Merges the activity logs from the source content IDs into the given
	 * content. If metrics is provided, only the given metrics are merged.
	 *
	 * @param list<int> $sourceIds
	 * @param list<string>|null $metrics
	 */
	public function mergeLogs(
		Entity $content,
		array $sourceIds,
		?array $metrics = null
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		if ($metrics === null)
		{
			$metrics = $this->getMetrics();
		}

		$handler->mergeLogs($content, $sourceIds, $metrics);
	}

	/**
	 * Removes all activity logs for the given content.
	 */
	public function removeLogs(Entity $content): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->removeLogs($content);
	}

	/**
	 * Rebuilds all reply metrics for the given content.
	 */
	public function rebuildReplyMetrics(Entity $content): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->rebuildReplyMetrics($content);
	}

	/**
	 * Rebuilds all reaction metrics for the given content.
	 */
	public function rebuildReactionMetrics(Entity $content): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->rebuildReactionMetrics($content);
	}

	/**
	 * Rebuilds all vote metrics for the given content.
	 */
	public function rebuildVoteMetrics(Entity $content): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$handler = $this->getHandler($content->getEntityContentType());
		if (!$handler)
		{
			return;
		}

		$handler->rebuildVoteMetrics($content);
	}

	/**
	 * @param array<string, int> $values
	 */
	public function handleLog(
		int $logDate,
		string $contentType,
		int $contentId,
		array $values,
		bool $increment
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		if (!$values)
		{
			return;
		}

		$row = $this->getActivityLogRow(
			$logDate,
			$contentType,
			$contentId,
			$values
		);
		if (!$row)
		{
			return;
		}

		$onDupe = $this->getActivityLogRowOnDupeClause($row, $increment);
		$this->db()->insert('xf_content_activity_log', $row, false, $onDupe);
	}

	/**
	 * @param list<array<string, int>> $values
	 */
	public function handleBulkLog(
		string $contentType,
		array $values,
		bool $increment
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$entries = [];
		foreach ($values AS $value)
		{
			$logDate = $value['log_date'] - ($value['log_date'] % 86400);
			$contentId = $value['content_id'];

			if (!isset($entries[$logDate][$contentId]))
			{
				$default = [
					'content_date' => $value['content_date'],
					'content_container_id' => $value['content_container_id'] ?? 0,
				];

				foreach ($this->getMetrics() AS $metric)
				{
					$default[$metric] = 0;
				}

				$entries[$logDate][$contentId] = $default;
			}

			unset(
				$value['log_date'],
				$value['content_id'],
				$value['content_date'],
				$value['content_container_id']
			);

			$entry = &$entries[$logDate][$contentId];
			foreach ($value AS $metric => $metricValue)
			{
				$entry[$metric] += $metricValue;
			}
		}

		$rows = [];
		foreach ($entries AS $logDate => $content)
		{
			foreach ($content AS $contentId => $contentValues)
			{
				$row = $this->getActivityLogRow(
					$logDate,
					$contentType,
					$contentId,
					$contentValues
				);
				if (!$row)
				{
					continue;
				}

				$rows[] = $row;
			}
		}

		if (!$rows)
		{
			return;
		}

		$firstRow = reset($rows);
		$onDupe = $this->getActivityLogRowOnDupeClause($firstRow, $increment);

		$this->db()->insertBulk(
			'xf_content_activity_log',
			$rows,
			false,
			$onDupe
		);
	}

	/**
	 * @return array<string, string|int>
	 */
	protected function getActivityLogRow(
		int $logDate,
		string $contentType,
		int $contentId,
		array $values
	): array
	{
		if (!isset($values['content_date']))
		{
			throw new \InvalidArgumentException('No content date was given');
		}

		$cutOff = $this->getCutOff();
		if ($logDate < $cutOff)
		{
			return [];
		}

		$row = [
			'log_date' => gmdate('Y-m-d', $logDate),
			'content_type' => $contentType,
			'content_id' => $contentId,
			'content_date' => $values['content_date'],
		];
		unset($values['content_date']);

		if (isset($values['content_container_id']))
		{
			$row['content_container_id'] = $values['content_container_id'];
			unset($values['content_container_id']);
		}

		$metrics = $this->getMetrics();
		$valid = false;
		foreach ($metrics AS $metric)
		{
			if (!isset($values[$metric]))
			{
				continue;
			}

			$row[$metric] = $values[$metric];

			if ($row[$metric] !== 0)
			{
				$valid = true;
			}
		}

		if (!$valid)
		{
			return [];
		}

		return $row;
	}

	/**
	 * @param array<string, string|int> $row
	 */
	protected function getActivityLogRowOnDupeClause(
		array $row,
		bool $increment
	): string
	{
		$onDupe = [];

		$metrics = $this->getMetrics();
		foreach ($metrics AS $metric)
		{
			if (!isset($row[$metric]))
			{
				continue;
			}

			if ($increment)
			{
				$onDupe[] = "{$metric} = {$metric} + VALUES({$metric})";
			}
			else
			{
				$onDupe[] = "{$metric} = VALUES({$metric})";
			}
		}

		return implode(', ', $onDupe);
	}

	public function handleUpdateContainerId(
		string $contentType,
		int $contentId,
		int $contentContainerId
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$this->db()->update(
			'xf_content_activity_log',
			['content_container_id' => $contentContainerId],
			'content_type = ? AND content_id = ?',
			[$contentType, $contentId]
		);
	}

	/**
	 * @param list<int> $sourceIds
	 * @param list<string> $metrics
	 */
	public function handleMergeLogs(
		string $contentType,
		int $targetId,
		int $targetDate,
		int $targetContainerId,
		array $sourceIds,
		array $metrics
	): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$metrics = array_intersect($metrics, $this->getMetrics());
		if (!$metrics)
		{
			return;
		}

		$metricColumns = implode(', ', $metrics);
		$metricSelectors = implode(', ', array_map(
			function ($metric)
			{
				return "COALESCE(SUM({$metric}), 0)";
			},
			$metrics
		));
		$onDupe = implode(', ', array_map(
			function ($metric)
			{
				return "{$metric} = {$metric} + VALUES({$metric})";
			},
			$metrics
		));

		$db = $this->db();
		$db->beginTransaction();

		$sourceIdsQuoted = $db->quote($sourceIds);

		$db->query(
			"INSERT INTO xf_content_activity_log
					(log_date, content_type, content_id, content_date, content_container_id, {$metricColumns})
				SELECT log_date, ?, ?, ?, ?, {$metricSelectors}
				FROM xf_content_activity_log
				WHERE content_type = ? AND content_id IN ({$sourceIdsQuoted})
				GROUP BY log_date
				ON DUPLICATE KEY UPDATE {$onDupe}",
			[$contentType, $targetId, $targetDate, $targetContainerId, $contentType]
		);

		$db->delete(
			'xf_content_activity_log',
			"content_type = ? AND content_id IN ({$sourceIdsQuoted})",
			[$contentType]
		);

		$db->commit();
	}

	public function handleRemoveLogs(string $contentType, int $contentId): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$this->db()->delete(
			'xf_content_activity_log',
			'content_type = ? AND content_id = ?',
			[$contentType, $contentId]
		);
	}

	public function pruneLogs(?int $cutOff = null): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$cutOff = $cutOff ?? $this->getCutOff();
		$date = new \DateTime("@{$cutOff}", new \DateTimeZone('UTC'));

		$this->db()->delete(
			'xf_content_activity_log',
			'log_date < ?',
			$date->format('Y-m-d')
		);
	}

	public function getLogLength(): int
	{
		return (int) $this->options()->activityLogLength;
	}

	public function isEnabled(): bool
	{
		return $this->getLogLength() !== 0;
	}

	public function getCutOff(): int
	{
		return \XF::$time - 86400 * $this->getLogLength();
	}

	/**
	 * @return list<string>
	 */
	public function getMetrics(): array
	{
		return [
			'view_count',
			'reply_count',
			'reaction_count',
			'reaction_score',
			'vote_count',
			'vote_score',
		];
	}

	public function getHandler(
		string $contentType,
		bool $throw = false
	): ?AbstractHandler
	{
		$handlerClass = $this->app()->getContentTypeFieldValue(
			$contentType,
			'activity_log_handler_class'
		);
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"No activity log handler for '{$contentType}'"
				);
			}

			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"Activity log handler does not exist for '{$contentType}' ({$handlerClass})"
				);
			}

			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($contentType);
	}
}
