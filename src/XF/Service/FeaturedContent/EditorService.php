<?php

namespace XF\Service\FeaturedContent;

use XF\App;
use XF\Entity\FeaturedContent;
use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Repository\FeaturedContentRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;
	use ImageTrait;

	/**
	 * @var AbstractHandler
	 */
	protected $handler;

	/**
	 * @var Entity
	 */
	protected $content;

	/**
	 * @var FeaturedContent
	 */
	protected $feature;

	public function __construct(App $app, FeaturedContent $feature)
	{
		parent::__construct($app);

		$this->handler = $this->getFeatureRepo()->getFeatureHandler(
			$feature->content_type,
			true
		);
		$this->content = $feature->Content;
		$this->feature = $feature;
	}

	public function getHandler(): AbstractHandler
	{
		return $this->handler;
	}

	public function getContent(): Entity
	{
		return $this->content;
	}

	public function getFeature(): FeaturedContent
	{
		return $this->feature;
	}

	public function setAutoFeatured(bool $autoFeatured = true)
	{
		$this->feature->auto_featured = $autoFeatured;
	}

	public function setTitle(string $title)
	{
		$this->feature->title = $title;
	}

	public function setSnippet(string $snippet)
	{
		$this->feature->snippet = $snippet;
	}

	public function setDate(int $date)
	{
		$this->feature->feature_date = $date;
	}

	public function setAlwaysVisible(bool $alwaysVisible)
	{
		$this->feature->always_visible = $alwaysVisible;
	}

	protected function finalSetup()
	{
	}

	protected function _validate(): array
	{
		$this->finalSetup();

		$this->feature->preSave();
		$errors = $this->feature->getErrors();

		if (!$this->validateImage($imageError))
		{
			$errors['image'] = $imageError;
		}

		return $errors;
	}

	protected function _save(): FeaturedContent
	{
		$this->db()->beginTransaction();

		$this->feature->save();

		if ($this->image)
		{
			$this->saveImage();
		}
		else if ($this->deleteImage)
		{
			$this->deleteImage();
		}

		$this->app->logger()->logModeratorAction(
			$this->content->getEntityContentType(),
			$this->content,
			'feature_edit'
		);

		$this->db()->commit();

		return $this->feature;
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
