<?php

namespace XF\AdminSearch;

use XF\Finder\AdminNavigationFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class AdminNavigationHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 20;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		if (!$previousMatchIds)
		{
			return $this->app->em()->getEmptyCollection();
		}

		$finder = $this->app->finder(AdminNavigationFinder::class);

		$finder
			->where('navigation_id', $previousMatchIds)
			->order($finder->caseInsensitive('navigation_id'))
			->limit($limit);

		$results = $finder->fetch();

		$visitor = \XF::visitor();
		$results = $results->filter(function ($record) use ($visitor)
		{
			if (!$record->link)
			{
				return false;
			}
			if ($record->admin_permission_id && !$visitor->hasAdminPermission($record->admin_permission_id))
			{
				return false;
			}
			if ($record->debug_only && !\XF::$debugMode)
			{
				return false;
			}
			if ($record->development_only && !\XF::$developmentMode)
			{
				return false;
			}

			return true;
		});

		return $results;
	}

	public function getRelatedPhraseGroups()
	{
		return ['admin_navigation'];
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return [
			'link' => $router->buildLink($record->link),
			'title' => $record->title,
			'extra' => $record->navigation_id,
		];
	}
}
