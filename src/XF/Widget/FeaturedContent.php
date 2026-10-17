<?php

namespace XF\Widget;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Finder\FeaturedContentFinder;
use XF\Http\Request;
use XF\Mvc\Entity\AbstractCollection;
use XF\Phrase;
use XF\Repository\FeaturedContentRepository;

class FeaturedContent extends AbstractWidget
{
	/**
	 * @var mixed[]
	 */
	protected $defaultOptions = [
		'contextual' => false,
		'contextual_hidden' => false,
		'limit' => 5,
		'style' => 'simple',
		'snippet_length' => 500,
		'content_type' => null,
		'content_container_id' => null,
	];

	/**
	 * @param string $context
	 *
	 * @return mixed[]
	 */
	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);

		if ($context == 'options')
		{
			$params['contentTypes'] = $this->getFeatureRepo()->getSupportedContentTypes();
		}

		return $params;
	}

	/**
	 * @return WidgetRenderer|null
	 */
	public function render()
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

		$limit = $options['limit'];
		$style = $options['style'];
		$snippetLength = $options['snippet_length'];
		$contentType = $options['content_type'];

		$featureRepo = $this->getFeatureRepo();

		$finder = $featureRepo->findFeaturedContent();
		$filters = $this->getFilters();
		$this->applyFilters($finder, $filters);

		$features = $finder->fetch(max($limit * 2, 10));
		$featureRepo->addContentToFeaturesForStyle($features, $style);

		$features = $this->filterFeatures($features);

		$total = $features->count();
		$features = $features->slice(0, $limit, true);
		$hasMore = $total > $features->count();

		$title = $this->getTitle();

		$router = $this->app->router('public');

		$linkParams = [];
		if ($contentType)
		{
			$linkParams['content_type'] = $contentType;
		}

		$link = $router->buildLink('featured', null, $linkParams);

		$viewParams = [
			'title' => $title,
			'link' => $link,
			'features' => $features,

			'style' => $style,
			'snippetLength' => $snippetLength,
			'hasMore' => $hasMore,
		];
		return $this->renderer('widget_featured_content', $viewParams);
	}

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
	 * @return array{content_type?: string, content_container_id?: int}
	 */
	protected function getFilters(): array
	{
		$filters = [];

		if ($this->options['content_type'])
		{
			$filters['content_type'] = $this->options['content_type'];
		}

		if ($this->options['content_container_id'])
		{
			$filters['content_container_id'] = $this->options['content_container_id'];
		}

		return $filters;
	}

	/**
	 * @param array{content_type?: string, content_container_id?: int} $filters
	 */
	protected function applyFilters(FeaturedContentFinder $finder, array $filters): void
	{
		if ($filters['content_type'] ?? null)
		{
			$finder->where('content_type', $filters['content_type']);
		}

		if ($filters['content_container_id'] ?? null)
		{
			$finder->where(
				'content_container_id',
				$filters['content_container_id']
			);
		}
	}

	/**
	 * @param AbstractCollection<\XF\Entity\FeaturedContent> $features
	 *
	 * @return AbstractCollection<\XF\Entity\FeaturedContent>
	 */
	protected function filterFeatures(AbstractCollection $features): AbstractCollection
	{
		return $features->filter(
			function (\XF\Entity\FeaturedContent $feature)
			{
				return $feature->canView() && !$feature->isIgnored();
			}
		);
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
			'limit' => 'uint',
			'style' => 'str',
			'snippet_length' => 'uint',
			'content_type' => 'str',
		]);

		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
