<?php

namespace XF\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\ActivityLogRepository;
use XF\Repository\TrendingContentRepository;
use XF\TrendingContent\AbstractHandler;

/**
 * COLUMNS
 * @property int|null $trending_result_id
 * @property string $order
 * @property int $duration
 * @property string $content_type
 * @property int $content_container_id
 * @property int $result_date
 * @property array $content_data
 */
class TrendingResult extends Entity
{
	/**
	 * @var int
	 */
	public const RESULT_TTL = 900;

	/**
	 * @var string
	 */
	public const ORDER_HOT = 'hot';

	/**
	 * @var string
	 */
	public const ORDER_TOP = 'top';

	/**
	 * @var int
	 */
	public const MAX_RESULTS = 200;

	public function isStale(): bool
	{
		return $this->result_date < \XF::$time - static::RESULT_TTL;
	}

	/**
	 * @return AbstractCollection|array<int, Entity>
	 */
	public function getContent(string $style, int $limit = 0): AbstractCollection
	{
		return $this->getTrendingContentRepo()->getResultContent(
			$this,
			$style,
			$limit
		);
	}

	public function renderContent(
		Entity $content,
		string $style,
		int $snippetLength = 0
	): string
	{
		$handler = $this->getContentHandler($content);
		return $handler
			? $handler->render($this, $content, $style, $snippetLength)
			: '';
	}

	protected function getContentHandler(Entity $content): ?AbstractHandler
	{
		return $this->getTrendingContentRepo()->getHandler(
			$content->getEntityContentType()
		);
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_trending_result';
		$structure->shortName = 'XF:TrendingContent';
		$structure->primaryKey = 'trending_result_id';
		$structure->columns = [
			'trending_result_id' => [
				'type' => self::UINT,
				'autoIncrement' => true,
				'nullable' => true,
			],
			'order' => [
				'type' => self::STR,
				'required' => true,
				'allowedValues' => \XF::repository(TrendingContentRepository::class)->getResultOrders(),
			],
			'duration' => [
				'type' => self::UINT,
				'required' => true,
				'min' => 1,
				'max' => ActivityLogRepository::MAX_RETENTION_DAYS,
			],
			'content_type' => [
				'type' => self::STR,
				'maxLength' => 25,
				'default' => '',
			],
			'content_container_id' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'result_date' => [
				'type' => self::UINT,
				'default' => \XF::$time,
			],
			'content_data' => [
				'type' => self::JSON_ARRAY,
				'default' => [],
			],
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}

	protected function getTrendingContentRepo(): TrendingContentRepository
	{
		return $this->repository(TrendingContentRepository::class);
	}
}
