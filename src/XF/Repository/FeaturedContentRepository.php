<?php

namespace XF\Repository;

use XF\Entity\FeaturedContent;
use XF\FeaturedContent\AbstractHandler;
use XF\Finder\FeaturedContentFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

use function in_array;

class FeaturedContentRepository extends Repository
{
	/**
	 * @return FeaturedContentFinder
	 */
	public function findFeaturedContent(bool $visibleOnly = true): Finder
	{
		$finder = $this->finder(FeaturedContentFinder::class)
			->setDefaultOrder('feature_date', 'desc');

		if ($visibleOnly)
		{
			$finder->where('content_visible', true);
		}

		return $finder;
	}

	/**
	 * @param AbstractCollection<FeaturedContent> $features
	 */
	public function addContentToFeaturesForStyle(
		AbstractCollection $features,
		string $style
	): void
	{
		$groupedContent = [];
		foreach ($features AS $featureId => $feature)
		{
			$contentType = $feature->content_type;
			$contentId = $feature->content_id;
			$groupedContent[$contentType][$featureId] = $contentId;
		}

		$handlers = [];
		foreach (array_keys($groupedContent) AS $contentType)
		{
			$handlers[$contentType] = $this->getFeatureHandler($contentType);
		}

		foreach ($groupedContent AS $contentType => $contentIds)
		{
			$handler = $handlers[$contentType] ?? null;
			if (!$handler)
			{
				continue;
			}

			$content = $handler->getContentForStyle($contentIds, $style);
			foreach ($contentIds AS $featureId => $contentId)
			{
				$feature = $features[$featureId];
				$featureContent = $content[$contentId] ?? null;
				$feature->setContent($featureContent);

				if ($featureContent && $featureContent->hasRelation('Feature'))
				{
					$featureContent->hydrateRelation('Feature', $feature);
				}
			}

			if ($this->areAttachmentsHydratedForStyle($style))
			{
				$imagelessContent = $content->filter(function (Entity $entity): bool
				{
					/** @var FeaturedContent $feature */
					$feature = $entity->Feature;
					return !$feature->image_date;
				});
				$handler->addAttachmentsToContentExternal($imagelessContent, $content);
			}
		}
	}

	/**
	 * @deprecated
	 */
	public function addContentToFeatures(AbstractCollection $features)
	{
		$this->addContentToFeaturesForStyle($features, 'article');
	}

	public function areAttachmentsHydratedForStyle(string $style): bool
	{
		return in_array($style, ['article', 'carousel'], true);
	}

	public function getFeatureHandler(
		string $contentType,
		bool $throw = false
	): ?AbstractHandler
	{
		$handlerClass = $this->app()->getContentTypeFieldValue(
			$contentType,
			'featured_content_handler_class'
		);
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"No featured content handler for '{$contentType}'"
				);
			}

			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException(
					"Featured content handler does not exist for '{$contentType}' ({$handlerClass})"
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
			$this->app()->getContentTypeField('featured_content_handler_class')
		);
	}
}
