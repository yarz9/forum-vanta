<?php

namespace XF\Import\Data;

use XF\Service\FeaturedContent\EditorService;

/**
 * @mixin \XF\Entity\FeaturedContent
 */
class FeaturedContent extends AbstractEmulatedData
{
	/**
	 * @var string|null
	 */
	protected $sourceImage = null;

	/**
	 * @var (callable(\XF\Mvc\Entity\Entity, \XF\Entity\FeaturedContent): void)|null
	 */
	protected $contentCallback;

	public function getImportType(): string
	{
		return 'featured_content';
	}

	public function getEntityShortName(): string
	{
		return 'XF:FeaturedContent';
	}

	public function setSourceImage(string $sourceImage): void
	{
		$this->sourceImage = $sourceImage;
	}

	/**
	 * @param callable(\XF\Mvc\Entity\Entity, \XF\Entity\FeaturedContent): void $callback
	 */
	public function setContentCallback(callable $callback): void
	{
		$this->contentCallback = $callback;
	}

	protected function postSave($oldId, $newId): void
	{
		if (!$this->sourceImage && !$this->contentCallback)
		{
			return;
		}

		$feature = $this->em()->find(\XF\Entity\FeaturedContent::class, $newId);
		if (!$feature)
		{
			return;
		}

		if ($this->sourceImage)
		{
			$editor = $this->app()->service(EditorService::class, $feature);
			$editor->setImage($this->sourceImage);
			if ($editor->validate())
			{
				$editor->save();
			}
		}

		if ($this->contentCallback)
		{
			($this->contentCallback)($feature->Content, $feature);
		}
	}
}
