<?php

namespace XF\Service\FeaturedContent;

use XF\App;
use XF\Entity\FeaturedContent;
use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Repository\FeaturedContentRepository;
use XF\Service\AbstractService;

class DeleterService extends AbstractService
{
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

	public function delete(): bool
	{
		$this->db()->beginTransaction();

		$result = $this->feature->delete();
		$this->handler->onContentUnfeature($this->content, $this->feature);

		$this->app->logger()->logModeratorAction(
			$this->content->getEntityContentType(),
			$this->content,
			'feature_delete'
		);

		$this->db()->commit();

		return $result;
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
