<?php

namespace XF\Behavior;

use XF\Entity\FeaturedContent;
use XF\Entity\FeatureTrait;
use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Behavior;
use XF\Repository\FeaturedContentRepository;
use XF\Service\FeaturedContent\CreatorService;

class Featurable extends Behavior
{
	protected function getDefaultConfig(): array
	{
		return [
			'stateField' => null,
		];
	}

	protected function verifyConfig()
	{
		if (!$this->contentType())
		{
			throw new \LogicException(
				'Structure must provide a contentType value'
			);
		}

		if ($this->config['stateField'] === null)
		{
			throw new \LogicException(
				'stateField config must be overridden; if no field is present, use an empty string'
			);
		}
	}

	public function postSave()
	{
		if ($this->entity->isInsert())
		{
			$handler = $this->getHandler();
			if ($handler && $handler->shouldAutoFeature($this->entity))
			{
				/** @var CreatorService $creator */
				$creator = $this->app()->service(
					CreatorService::class,
					$this->entity
				);
				$creator->setAutoFeatured();
				$creator->save();
			}
		}
		else
		{
			if ($this->config['stateField'])
			{
				$visibilityChange = $this->entity->isStateChanged(
					$this->config['stateField'],
					'visible'
				);
				/** @var FeatureTrait $entity */
				$entity = $this->entity;
				if (
					$visibilityChange &&
					$entity->isFeatured() &&
					$entity->Feature
				)
				{
					/** @var FeaturedContent $feature */
					$feature = $entity->Feature;
					$feature->fastUpdate(
						'content_visible',
						($visibilityChange == 'enter')
					);
				}
			}
		}
	}

	public function postDelete()
	{
		/** @var FeatureTrait $entity */
		$entity = $this->entity;
		if ($entity->isFeatured() && $entity->Feature)
		{
			/** @var FeaturedContent $feature */
			$feature = $entity->Feature;
			$feature->delete();
		}
	}

	protected function getHandler(): ?AbstractHandler
	{
		/** @var FeaturedContentRepository $featureRepo */
		$featureRepo = $this->repository(FeaturedContentRepository::class);
		return $featureRepo->getFeatureHandler($this->contentType());
	}
}
