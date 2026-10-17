<?php

namespace XF\Repository;

use XF\Entity\TrendingResult;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\TrendingContent\AbstractHandler;

use function array_slice, in_array;

class TrendingContentRepository extends Repository
{
	public function getResult(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0,
		bool $autoEnqueue = true
	): ?TrendingResult
	{
		$this->validateResultOptions(
			$order,
			$duration,
			$contentType,
			$contentContainerId
		);

		if (!$this->isActivityLogEnabled())
		{
			return null;
		}

		$finder = $this->finder(TrendingResult::class)
			->where('order', $order)
			->where('duration', $duration)
			->where('content_type', $contentType)
			->where('content_container_id', $contentContainerId)
			->order('result_date', 'DESC');
		/** @var TrendingResult $trendingResult */
		$trendingResult = $finder->fetchOne();

		if ($autoEnqueue && !$trendingResult || $trendingResult->isStale())
		{
			$this->enqueueResultJob(
				$order,
				$duration,
				$contentType,
				$contentContainerId
			);
		}

		return $trendingResult;
	}

	public function enqueueResultJob(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0
	): void
	{
		$this->validateResultOptions(
			$order,
			$duration,
			$contentType,
			$contentContainerId
		);

		if (!$this->isActivityLogEnabled())
		{
			return;
		}

		$key = md5("{$order}_{$duration}_{$contentType}_{$contentContainerId}");

		$jobManager = $this->app()->jobManager();
		$jobManager->enqueueUnique(
			"trendingContent_{$key}",
			\XF\Job\TrendingResult::class,
			[
				'order' => $order,
				'duration' => $duration,
				'content_type' => $contentType,
				'content_container_id' => $contentContainerId,
			],
			false
		);
	}

	public function createResult(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0
	): ?TrendingResult
	{
		$this->validateResultOptions(
			$order,
			$duration,
			$contentType,
			$contentContainerId
		);

		if (!$this->isActivityLogEnabled())
		{
			return null;
		}

		$trendingContent = $this->em->create(TrendingResult::class);

		$trendingContent->order = $order;
		$trendingContent->duration = $duration;
		$trendingContent->content_type = $contentType;
		$trendingContent->content_container_id = $contentContainerId;
		$trendingContent->content_data = $this->normalizeContentData(
			$this->fetchContentData(
				$order,
				$duration,
				$contentType,
				$contentContainerId,
				TrendingResult::MAX_RESULTS
			)
		);

		if (!$trendingContent->preSave())
		{
			return null;
		}

		$trendingContent->save();

		return $trendingContent;
	}

	/**
	 * @return list<array{
	 *     content_type: string,
	 *     content_id: int,
	 *     content_date: int,
	 *     content_container_id: int,
	 *     raw_score: float,
	 * }>
	 */
	protected function fetchContentData(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0,
		?int $limit = null,
		int $offset = 0
	): array
	{
		return $this->db()->fetchAll(
			$this->db()->limit(
				$this->getContentDataQuery(
					$order,
					$duration,
					$contentType,
					$contentContainerId
				),
				$limit,
				$offset
			)
		);
	}

	protected function getContentDataQuery(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0
	): string
	{
		$scoreColumn = $this->getContentDataScoreColumn($order);

		$conditions = $this->getContentDataConditions(
			$duration,
			$contentType,
			$contentContainerId
		);
		$conditions = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

		return "SELECT content_type,
					content_id,
					content_date,
					content_container_id,
					{$scoreColumn} AS raw_score
				FROM xf_content_activity_log
				{$conditions}
				GROUP BY content_type, content_id
				ORDER BY raw_score DESC,
					content_date DESC,
					content_type DESC,
					content_id DESC";
	}

	protected function getContentDataScoreColumn(string $order): string
	{
		$score = [];
		$weights = $this->app()->options()->trendingContentWeights;

		foreach ($this->getActivityLogRepo()->getMetrics() AS $metric)
		{
			$weight = $weights[$metric] ?? 0;
			if ($weight === 0)
			{
				continue;
			}

			$score[] = "({$metric} * {$weight})";
		}

		$score = implode(' + ', $score);

		if ($order === TrendingResult::ORDER_HOT)
		{
			$decay = 0.5;
			$halfLife = $this->app()->options()->trendingContentHalfLife;
			$scale = 86400 * $halfLife;
			$origin = \XF::$time - (\XF::$time % 86400);
			$score = "EXP((LN({$decay}) / {$scale}) * ABS({$origin} - UNIX_TIMESTAMP(log_date))) * ({$score})";
		}

		return "SUM({$score})";
	}

	/**
	 * @return list<string>
	 */
	protected function getContentDataConditions(
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0
	): array
	{
		$conditions = [];

		$cutOff = gmdate('Y-m-d', \XF::$time - ($duration - 1) * 86400);
		$conditions[] = "log_date >= '{$cutOff}'";

		if ($contentType !== '')
		{
			$conditions[] = "content_type = '{$contentType}'";

			if ($contentContainerId !== 0)
			{
				$conditions[] = "content_container_id = {$contentContainerId}";
			}
		}

		return $conditions;
	}

	/**
	 * @param list<array{
	 *     content_type: string,
	 *     content_id: int,
	 *     content_date: int,
	 *     content_container_id: int,
	 *     raw_score: float,
	 * }> $contentData
	 *
	 * @return list<array{
	 *     content_type: string,
	 *     content_id: int,
	 *     content_date: int,
	 *     content_container_id: int,
	 *     raw_score: float,
	 *     score: int,
	 * }>
	 */
	protected function normalizeContentData(array $contentData): array
	{
		if (!$contentData)
		{
			return [];
		}

		$normalizedContentData = [];

		$rawScores = array_column($contentData, 'raw_score');
		$minScore = min($rawScores);
		$maxScore = max($rawScores);
		$deltaScore = (float) $maxScore - $minScore;

		foreach ($contentData AS $item)
		{
			if ($deltaScore === 0.0)
			{
				$item['score'] = 50;
			}
			else
			{
				$item['score'] = round(
					(($item['raw_score'] - $minScore) / $deltaScore) * 100
				);
			}

			$normalizedContentData[] = $item;
		}

		return $normalizedContentData;
	}

	public function pruneResults(?int $cutOff = null): void
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - TrendingResult::RESULT_TTL;
		}

		$this->db()->delete('xf_trending_result', 'result_date < ?', $cutOff);
	}

	/**
	 * @return AbstractCollection|array<int, Entity>
	 */
	public function getResultContent(
		TrendingResult $trendingResult,
		string $style,
		int $limit = 0
	): AbstractCollection
	{
		$contentData = $trendingResult->content_data;
		if ($limit !== 0)
		{
			$contentData = array_slice($contentData, 0, $limit);
		}

		$groupedResults = [];
		foreach ($contentData AS $item)
		{
			$contentType = $item['content_type'];
			$contentId = $item['content_id'];
			$groupedResults[$contentType][] = $contentId;
		}

		$handlers = [];
		foreach (array_keys($groupedResults) AS $contentType)
		{
			$handlers[$contentType] = $this->getHandler($contentType);
		}

		$groupedContent = [];
		foreach ($groupedResults AS $contentType => $contentIds)
		{
			$handler = $handlers[$contentType] ?? null;
			if (!$handler)
			{
				continue;
			}

			$content = $handler->getContent($contentIds, $style);
			$content = $handler->filterContent($content);

			if ($this->areAttachmentsHydratedForStyle($style))
			{
				$handler->addAttachmentsToContent($content);
			}

			$groupedContent[$contentType] = $content;
		}

		$content = [];
		foreach ($contentData AS $item)
		{
			$contentType = $item['content_type'];
			$contentId = $item['content_id'];
			$contentItem = $groupedContent[$contentType][$contentId] ?? null;
			if (!$contentItem)
			{
				continue;
			}

			$content[] = $contentItem;
		}

		return $this->em->getBasicCollection($content);
	}

	public function areAttachmentsHydratedForStyle(string $style): bool
	{
		return in_array($style, ['article', 'carousel'], true);
	}

	public function getHandler(
		string $contentType,
		bool $throw = false
	): ?AbstractHandler
	{
		$handlerClass = $this->app()->getContentTypeFieldValue(
			$contentType,
			'trending_content_handler_class'
		);
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"No trending content handler for '{$contentType}'"
				);
			}

			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"Trending content handler does not exist for '{$contentType}' ({$handlerClass})"
				);
			}

			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($contentType);
	}

	/**
	 * @return list<string>
	 */
	public function getSupportedContentTypes(): array
	{
		return array_keys(
			$this->app()->getContentTypeField('trending_content_handler_class')
		);
	}

	/**
	 * @return list<string>
	 */
	public function getResultOrders(): array
	{
		return [
			TrendingResult::ORDER_HOT,
			TrendingResult::ORDER_TOP,
		];
	}

	public function isActivityLogEnabled(): bool
	{
		return $this->getActivityLogRepo()->isEnabled();
	}

	protected function validateResultOptions(
		string $order,
		int $duration,
		string $contentType = '',
		int $contentContainerId = 0
	): void
	{
		if (!in_array($order, $this->getResultOrders()))
		{
			throw new \InvalidArgumentException(
				"Invalid trending result order: {$order}"
			);
		}

		if ($duration < 1 || $duration > ActivityLogRepository::MAX_RETENTION_DAYS)
		{
			throw new \InvalidArgumentException(
				"Invalid trending result duration: {$duration}"
			);
		}

		if ($contentType && !in_array($contentType, $this->getSupportedContentTypes()))
		{
			throw new \InvalidArgumentException(
				"Invalid trending result content type: {$contentType}"
			);
		}

		if ($contentContainerId < 0)
		{
			throw new \InvalidArgumentException(
				"Invalid trending result content container ID: {$contentContainerId}"
			);
		}
	}

	protected function getActivityLogRepo(): ActivityLogRepository
	{
		return $this->repository(ActivityLogRepository::class);
	}
}
