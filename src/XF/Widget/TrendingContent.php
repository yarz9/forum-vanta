<?php

namespace XF\Widget;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Entity\TrendingResult;
use XF\Http\Request;
use XF\Phrase;
use XF\Repository\ActivityLogRepository;
use XF\Repository\TrendingContentRepository;

class TrendingContent extends AbstractWidget
{
	/**
	 * @var array<string, mixed>
	 */
	protected $defaultOptions = [
		'contextual' => false,
		'contextual_hidden' => false,
		'order' => TrendingResult::ORDER_HOT,
		'duration' => 7,
		'limit' => 5,
		'style' => 'simple',
		'snippet_length' => 500,
		'content_type' => '',
		'content_container_id' => 0,
	];

	/**
	 * @param string $context
	 *
	 * @return array<string, mixed>
	 */
	protected function getDefaultTemplateParams($context): array
	{
		$params = parent::getDefaultTemplateParams($context);

		if ($context == 'options')
		{
			$trendingContentRepo = $this->getTrendingContentRepo();
			$params['orders'] = $trendingContentRepo->getResultOrders();
			$params['contentTypes'] = $trendingContentRepo->getSupportedContentTypes();
		}

		return $params;
	}

	public function render(): ?WidgetRenderer
	{
		$options = $this->options;
		if ($options['contextual'])
		{
			$contextualOptions = $this->getContextualOptions();
			if ($contextualOptions)
			{
				$options = array_replace($options, $contextualOptions);
			}
			else if ($options['contextual_hidden'])
			{
				return null;
			}
		}

		$trendingContentRepo = $this->getTrendingContentRepo();
		$result = $trendingContentRepo->getResult(
			$options['order'],
			$options['duration'],
			$options['content_type'],
			$options['content_container_id']
		);
		if (!$result)
		{
			return null;
		}

		$style = $options['style'];
		$snippetLength = $options['snippet_length'];

		$content = $result->getContent($style, max($options['limit'] * 2, 10));
		$content = $content->slice(0, $options['limit']);

		$title = $this->getTitle();

		$viewParams = [
			'title' => $title,
			'result' => $result,
			'content' => $content,

			'style' => $style,
			'snippetLength' => $snippetLength,
		];
		return $this->renderer('widget_trending_content', $viewParams);
	}

	/**
	 * @return array{content_type: string, content_container_id: int}
	 */
	protected function getContextualOptions(): array
	{
		$forum = $this->contextParams['forum'] ?? null;
		if ($forum && $forum instanceof Forum)
		{
			return [
				'content_type' => 'thread',
				'content_container_id' => $forum->node_id,
			];
		}

		$thread = $this->contextParams['thread'] ?? null;
		if ($thread && $thread instanceof Thread)
		{
			return [
				'content_type' => 'thread',
				'content_container_id' => $thread->node_id,
			];
		}

		return [];
	}

	/**
	 * @param Phrase|null $error
	 */
	public function verifyOptions(
		Request $request,
		array &$options,
		&$error = null
	): bool
	{
		$options = $request->filter([
			'contextual' => 'bool',
			'contextual_hidden' => 'bool',
			'order' => 'str',
			'duration' => 'uint',
			'limit' => 'uint',
			'style' => 'str',
			'snippet_length' => 'uint',
			'content_type' => 'str',
		]);

		if ($options['duration'] < 1)
		{
			$options['duration'] = 1;
		}
		else if ($options['duration'] > ActivityLogRepository::MAX_RETENTION_DAYS)
		{
			$options['duration'] = ActivityLogRepository::MAX_RETENTION_DAYS;
		}

		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}

	protected function getTrendingContentRepo(): TrendingContentRepository
	{
		return $this->repository(TrendingContentRepository::class);
	}
}
