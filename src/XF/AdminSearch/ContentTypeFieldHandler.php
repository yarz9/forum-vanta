<?php

namespace XF\AdminSearch;

use XF\Entity\ContentTypeField;
use XF\Finder\ContentTypeFieldFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class ContentTypeFieldHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 80;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		if (!preg_match('/^[\da-z_]+$/i', $text))
		{
			return false;
		}

		$finder = $this->app->finder(ContentTypeFieldFinder::class)->limit($limit);
		$escapedLike = $finder->escapeLike($text, '%?%');

		$finder->whereOr([
			['content_type', 'LIKE', $escapedLike],
			['field_name', 'LIKE', $escapedLike],
		]);

		return $finder->fetch();
	}

	public function getTemplateData(Entity $tag)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		/** @var ContentTypeField $tag */
		return [
			'link' => $router->buildLink('content-types/edit', $tag),
			'title' => sprintf(
				'%s: %s',
				$tag->content_type,
				$tag->field_name
			),
			'extra' => $tag->field_value,
		];
	}
}
