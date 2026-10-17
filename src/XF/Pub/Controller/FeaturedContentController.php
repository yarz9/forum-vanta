<?php

namespace XF\Pub\Controller;

use XF\Entity\FeaturedContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\Repository\FeaturedContentRepository;

use function count, in_array;

class FeaturedContentController extends AbstractController
{
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		return $this->rerouteController(self::class, 'list', $params);
	}

	public function actionList(ParameterBag $params): AbstractReply
	{
		if ($this->responseType == 'rss')
		{
			return $this->getFeaturedContentRss();
		}

		$featureRepo = $this->getFeatureRepo();

		$finder = $featureRepo->findFeaturedContent();
		$filters = $this->getFilterInput();
		$this->applyFilters($finder, $filters);

		$page = $this->filterPage($params->page);
		$perPage = $this->options()->discussionsPerPage; // TODO: custom option?
		$total = $finder->total();

		$this->assertValidPage($page, $perPage, $total, 'featured');
		$this->assertCanonicalUrl(
			$this->buildLink('featured', null, ['page' => $page])
		);

		/** @var ArrayCollection $features */
		$features = $finder->limitByPage($page, $perPage)->fetch();
		$featureRepo->addContentToFeaturesForStyle($features, 'article');
		$features = $this->filterFeatures($features);

		$displayFilters = $this->shouldDisplayFilters();

		$viewParams = [
			'features' => $features,

			'displayFilters' => $displayFilters,
			'filters' => $filters,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view(
			'XF:FeaturedContent\List',
			'featured_content_list',
			$viewParams
		);
	}

	protected function getFeaturedContentRss(): AbstractReply
	{
		$featureRepo = $this->getFeatureRepo();

		$finder = $featureRepo->findFeaturedContent();
		$filters = $this->getFilterInput();
		$this->applyFilters($finder, $filters);

		$limit = $this->options()->discussionsPerPage;

		$features = $finder->fetch($limit * 3);
		$featureRepo->addContentToFeaturesForStyle($features, 'article');
		$features = $this->filterFeatures($features);
		$features = $features->slice(0, $limit);

		$viewParams = [
			'features' => $features,
			'filters' => $filters,
		];
		return $this->view(
			'XF:FeaturedContent\Rss',
			'',
			$viewParams
		);
	}

	protected function shouldDisplayFilters(): bool
	{
		$contentTypes = $this->getFeatureRepo()->getSupportedContentTypes();
		return count($contentTypes) > 1;
	}

	public function actionFilters(): AbstractReply
	{
		$filters = $this->getFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink('featured', null, $filters));
		}

		$contentTypes = $this->getFeatureRepo()->getSupportedContentTypes();

		$viewParams = [
			'filters' => $filters,
			'contentTypes' => $contentTypes,
		];
		return $this->view(
			'XF:FeaturedContent\Filters',
			'featured_content_filters',
			$viewParams
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getFilterInput(): array
	{
		$filters = [];

		$input = $this->filter([
			'content_type' => 'str',
		]);

		if (in_array(
			$input['content_type'],
			$this->getFeatureRepo()->getSupportedContentTypes()
		))
		{
			$filters['content_type'] = $input['content_type'];
		}

		return $filters;
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	protected function applyFilters(
		Finder $finder,
		array $filters
	)
	{
		if (!empty($filters['content_type']))
		{
			$finder->where('content_type', $filters['content_type']);
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
			function (FeaturedContent $feature)
			{
				return $feature->canView() && !$feature->isIgnored();
			}
		);
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}

	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('viewing_featured_content');
	}
}
