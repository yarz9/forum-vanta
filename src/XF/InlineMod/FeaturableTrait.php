<?php

namespace XF\InlineMod;

use XF\Mvc\Entity\Entity;
use XF\Service\FeaturedContent\CreatorService;
use XF\Service\FeaturedContent\DeleterService;

trait FeaturableTrait
{
	public function addPossibleFeatureActions(
		AbstractHandler $handler,
		array &$actions,
		string $featureTitle,
		string $unfeatureTitle,
		string $canApply
	)
	{
		$actions['feature'] = $handler->getSimpleActionHandler(
			$featureTitle,
			$canApply,
			function (Entity $entity)
			{
				if ($entity->isFeatured())
				{
					return;
				}

				$creator = $this->app->service(
					CreatorService::class,
					$entity
				);
				$creator->save();
			}
		);

		$actions['unfeature'] = $handler->getSimpleActionHandler(
			$unfeatureTitle,
			$canApply,
			function (Entity $entity)
			{
				if (!$entity->isFeatured())
				{
					return;
				}

				$deleter = $this->app->service(
					DeleterService::class,
					$entity->Feature
				);
				$deleter->delete();
			}
		);
	}
}
