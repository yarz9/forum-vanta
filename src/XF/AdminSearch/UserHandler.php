<?php

namespace XF\AdminSearch;

use XF\Finder\UserFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;
use XF\Util\Url;

class UserHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 30;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		$finder = $this->app->finder(UserFinder::class);

		$conditions = [
			['username', 'like', $finder->escapeLike($text, '%?%')],
			['email', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['user_id', $previousMatchIds];
		}

		$finder
			->whereOr($conditions)
			->order('username')
			->limit($limit);

		return $finder->fetch();
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return [
			'link' => $router->buildLink('users/edit', $record),
			'title' => $record->username,
			'extra' => Url::emailToUtf8($record->email, false),
		];
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('user');
	}
}
