<?php

namespace XF\AdminSearch;

use XF\Finder\UserGroupFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class UserGroupHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 40;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		$finder = $this->app->finder(UserGroupFinder::class);

		$conditions = [
			['title', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['user_group_id', $previousMatchIds];
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
			'link' => $router->buildLink('user-groups/edit', $record),
			'title' => $record->title,
		];
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('userGroup');
	}
}
