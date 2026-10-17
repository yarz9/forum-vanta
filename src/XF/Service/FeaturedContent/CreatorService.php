<?php

namespace XF\Service\FeaturedContent;

use XF\App;
use XF\Entity\FeaturedContent;
use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Repository\FeaturedContentRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
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

	public function __construct(App $app, Entity $content)
	{
		parent::__construct($app);

		$this->setContent($content);
	}

	protected function setContent(Entity $content)
	{
		$handler = $this->getFeatureRepo()->getFeatureHandler(
			$content->getEntityContentType(),
			true
		);

		/** @var FeaturedContent $featuredContent */
		$feature = $this->em()->create(FeaturedContent::class);

		$feature->content_type = $content->getEntityContentType();
		$feature->content_id = $content->getEntityId();
		$feature->content_container_id = $handler->getContentContainerId($content);
		$feature->content_user_id = $handler->getContentUserId($content);
		$feature->content_username = $handler->getContentUsername($content);
		$feature->content_date = $handler->getContentDate($content);
		$feature->content_visible = $handler->getContentVisibility($content);

		$feature->feature_user_id = \XF::visitor()->user_id;

		$this->handler = $handler;
		$this->content = $content;
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
		$this->saveImage();

		$this->handler->onContentFeature($this->content, $this->feature);

		$this->app->logger()->logModeratorAction(
			$this->content->getEntityContentType(),
			$this->content,
			'feature_create'
		);

		$this->db()->commit();

		return $this->feature;
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
