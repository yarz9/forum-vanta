<?php

namespace XF\ActivityLog;

use XF\Entity\ContainableInterface;
use XF\Entity\DatableInterface;
use XF\Mvc\Entity\Entity;
use XF\Repository\ActivityLogRepository;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * Determines the date the given content was created.
	 *
	 * @param T $content
	 */
	protected function getContentDate(Entity $content): int
	{
		if (!($content instanceof DatableInterface))
		{
			throw new \LogicException(
				'Could not determine content date; please override'
			);
		}

		return $content->getContentDate();
	}

	/**
	 * Determines the container ID column of the given content.
	 *
	 * @param T $content
	 */
	protected function getContentContainerIdColumn(Entity $content): string
	{
		if (!($content instanceof ContainableInterface))
		{
			throw new \LogicException(
				'Could not determine content container ID column; please override'
			);
		}

		return $content->getContentContainerIdColumn();
	}

	/**
	 * Determines the container ID of the given content.
	 *
	 * @param T $content
	 */
	protected function getContentContainerId(Entity $content): int
	{
		if (!($content instanceof ContainableInterface))
		{
			throw new \LogicException(
				'Could not determine content container ID; please override'
			);
		}

		return $content->getContentContainerId();
	}

	/**
	 * Logs activity for the given content. The values should be a map of metrics
	 * to values.
	 *
	 * @param T $content
	 * @param array<string, int> $values
	 */
	public function log(
		Entity $content,
		int $logDate,
		array $values,
		bool $increment
	): void
	{
		$values['content_date'] = $this->getContentDate($content);
		$values['content_container_id'] = $this->getContentContainerId($content);

		$this->getActivityLogRepo()->handleLog(
			$logDate,
			$this->contentType,
			$content->getEntityId(),
			$values,
			$increment
		);
	}

	/**
	 * Logs multiple entries. The values should be a list of log entries,
	 * including a log date, content ID, content date, content container ID, and
	 * metrics.
	 *
	 * @param list<array<string, int>> $values
	 */
	public function bulkLog(array $values, bool $increment): void
	{
		$this->getActivityLogRepo()->handleBulkLog(
			$this->contentType,
			$values,
			$increment
		);
	}

	/**
	 * Updates the container ID for the given content if it has changed.
	 *
	 * @param T $content
	 */
	public function updateContainerId(Entity $content, bool $force = false): void
	{
		if (
			!$content->isChanged($this->getContentContainerIdColumn($content)) &&
			!$force
		)
		{
			return;
		}

		$this->getActivityLogRepo()->handleUpdateContainerId(
			$this->contentType,
			$content->getEntityId(),
			$this->getContentContainerId($content)
		);
	}

	/**
	 * Merges the activity logs from the source content IDs into the given
	 * content. If metrics is provided, only the given metrics are merged.
	 *
	 * @param T $target
	 * @param list<int> $sourceIds
	 * @param list<string> $metrics
	 */
	public function mergeLogs(
		Entity $target,
		array $sourceIds,
		array $metrics
	): void
	{
		$this->getActivityLogRepo()->handleMergeLogs(
			$this->contentType,
			$target->getEntityId(),
			$this->getContentDate($target),
			$this->getContentContainerId($target),
			$sourceIds,
			$metrics
		);
	}

	/**
	 * Removes all activity logs for the given content.
	 *
	 * @param T $content
	 */
	public function removeLogs(Entity $content): void
	{
		$this->getActivityLogRepo()->handleRemoveLogs(
			$this->contentType,
			$content->getEntityId()
		);
	}

	/**
	 * Rebuilds all reply metrics for the given content.
	 *
	 * @param T $content
	 */
	public function rebuildReplyMetrics(Entity $content): void
	{
		$contentId = $content->getEntityId();
		$contentDate = $this->getContentDate($content);
		$contentContainerId = $this->getContentContainerId($content);

		$db = \XF::db();
		$db->beginTransaction();

		$db->update(
			'xf_content_activity_log',
			[
				'reply_count' => 0,
			],
			'content_type = ? AND content_id = ?',
			[$this->contentType, $contentId]
		);

		$values = [];
		$replyMetrics = $this->getReplyMetrics($content);
		foreach ($replyMetrics AS $logDate => $replyMetric)
		{
			$values[] = [
				'log_date' => strtotime($logDate),
				'content_id' => $contentId,
				'content_date' => $contentDate,
				'content_container_id' => $contentContainerId,
				'reply_count' => $replyMetric['reply_count'],
			];
		}

		$this->bulkLog($values, false);

		$db->commit();
	}

	/**
	 * @param T $content
	 *
	 * @return array<string, string|int>
	 */
	protected function getReplyMetrics(Entity $content): array
	{
		return [];
	}

	/**
	 * @param array<string, mixed> $params
	 *
	 * @return array<string, string|int>
	 */
	protected function getReplyMetricsSimple(
		string $table,
		string $dateColumn,
		string $where,
		array $params
	): array
	{
		$cutOff = $this->getActivityLogRepo()->getCutOff();
		$params[] = $cutOff;

		return \XF::db()->fetchAllKeyed(
			"SELECT DATE(FROM_UNIXTIME({$dateColumn})) AS log_date,
					COUNT(*) AS reply_count
				FROM {$table}
				WHERE {$where} AND {$dateColumn} >= ?
				GROUP BY log_date
				ORDER BY log_date ASC",
			'log_date',
			$params
		);
	}

	/**
	 * Rebuilds all reaction metrics for the given content.
	 *
	 * @param T $content
	 */
	public function rebuildReactionMetrics(Entity $content): void
	{
		$contentId = $content->getEntityId();
		$contentDate = $this->getContentDate($content);
		$contentContainerId = $this->getContentContainerId($content);

		$db = \XF::db();
		$db->beginTransaction();

		$db->update(
			'xf_content_activity_log',
			[
				'reaction_count' => 0,
				'reaction_score' => 0,
			],
			'content_type = ? AND content_id = ?',
			[$this->contentType, $contentId]
		);

		$values = [];
		$reactionMetrics = $this->getReactionMetrics($content);
		foreach ($reactionMetrics AS $logDate => $reactionMetric)
		{
			$values[] = [
				'log_date' => strtotime($logDate),
				'content_id' => $contentId,
				'content_date' => $contentDate,
				'content_container_id' => $contentContainerId,
				'reaction_count' => $reactionMetric['reaction_count'],
				'reaction_score' => $reactionMetric['reaction_score'],
			];
		}

		$this->bulkLog($values, false);

		$db->commit();
	}

	/**
	 * @param T $content
	 *
	 * @return array<string, string|int>
	 */
	protected function getReactionMetrics(Entity $content): array
	{
		$contentType = $content->getEntityContentType();
		$contentId = $content->getEntityId();
		$cutOff = $this->getActivityLogRepo()->getCutOff();

		return \XF::db()->fetchAllKeyed(
			'SELECT DATE(FROM_UNIXTIME(reaction_content.reaction_date)) AS log_date,
					COUNT(*) AS reaction_count,
					SUM(reaction.reaction_score) AS reaction_score
				FROM xf_reaction_content AS reaction_content
				LEFT JOIN xf_reaction AS reaction
					ON (reaction.reaction_id = reaction_content.reaction_id)
				WHERE reaction_content.content_type = ?
					AND reaction_content.content_id = ?
					AND reaction_content.reaction_date >= ?
				GROUP BY log_date
				ORDER BY log_date ASC',
			'log_date',
			[$contentType, $contentId, $cutOff]
		);
	}

	/**
	 * Rebuilds all vote metrics for the given content.
	 *
	 * @param T $content
	 */
	public function rebuildVoteMetrics(Entity $content): void
	{
		$contentId = $content->getEntityId();
		$contentDate = $this->getContentDate($content);
		$contentContainerId = $this->getContentContainerId($content);

		$db = \XF::db();
		$db->beginTransaction();

		$db->update(
			'xf_content_activity_log',
			[
				'vote_count' => 0,
				'vote_score' => 0,
			],
			'content_type = ? AND content_id = ?',
			[$this->contentType, $contentId]
		);

		$values = [];
		$voteMetrics = $this->getVoteMetrics($content);
		foreach ($voteMetrics AS $logDate => $voteMetric)
		{
			$values[] = [
				'log_date' => strtotime($logDate),
				'content_id' => $contentId,
				'content_date' => $contentDate,
				'content_container_id' => $contentContainerId,
				'vote_count' => $voteMetric['vote_count'],
				'vote_score' => $voteMetric['vote_score'],
			];
		}

		$this->bulkLog($values, false);

		$db->commit();
	}

	/**
	 * @param T $content
	 *
	 * @return array<string, string|int>
	 */
	protected function getVoteMetrics(Entity $content): array
	{
		$contentType = $content->getEntityContentType();
		$contentId = $content->getEntityId();
		$cutOff = $this->getActivityLogRepo()->getCutOff();

		return \XF::db()->fetchAllKeyed(
			'SELECT DATE(FROM_UNIXTIME(content_vote.vote_date)) AS log_date,
					COUNT(*) AS vote_count,
					SUM(content_vote.score) AS vote_score
				FROM xf_content_vote AS content_vote
				WHERE content_vote.content_type = ?
					AND content_vote.content_id = ?
					AND content_vote.vote_date >= ?
				GROUP BY log_date
				ORDER BY log_date ASC',
			'log_date',
			[$contentType, $contentId, $cutOff]
		);
	}

	protected function getActivityLogRepo(): ActivityLogRepository
	{
		return \XF::repository(ActivityLogRepository::class);
	}
}
