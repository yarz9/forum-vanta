<?php

namespace XF\AdminSearch;

use XF\Finder\NodeFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class NodeHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 45;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		$finder = $this->app->finder(NodeFinder::class);

		$conditions = [
			['title', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['node_id', $previousMatchIds];
		}

		$finder
			->whereOr($conditions)
			->order('title')
			->limit($limit);

		return $finder->fetch();
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return [
			'link' => $router->buildLink('nodes/edit', $record),
			'title' => $record->title,
		];
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('node');
	}
}
