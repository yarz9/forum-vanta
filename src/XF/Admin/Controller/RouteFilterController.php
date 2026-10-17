<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\RouteFilter;
use XF\Finder\RouteFilterFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Router;
use XF\Repository\RouteFilterRepository;

class RouteFilterController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('option');
	}

	public function actionIndex()
	{
		$viewParams = [
			'routeFilters' => $this->getRouteFilterRepo()
				->findRouteFiltersForList()
				->fetch(),
		];
		return $this->view('XF:RouteFilter\Listing', 'route_filter_list', $viewParams);
	}

	protected function routeFilterAddEdit(RouteFilter $routeFilter)
	{
		/** @var Router $publicRouter */
		$publicRouter = $this->app->container('router.public');

		$fullIndex = $publicRouter->buildLink('full:index');
		$fullThreadLink = $publicRouter->buildLink('full:threads', ['thread_id' => 1, 'title' => 'example']);
		$routeValue = str_replace([$fullIndex, '?'], '', $fullThreadLink);

		$viewParams = [
			'routeFilter' => $routeFilter,
			'fullThreadLink' => $fullThreadLink,
			'routeValue' => $routeValue,
		];
		return $this->view('XF:RouteFilter\Edit', 'route_filter_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$routeFilter = $this->assertRouteFilterExists($params['route_filter_id']);
		return $this->routeFilterAddEdit($routeFilter);
	}

	public function actionAdd()
	{
		$routeFilter = $this->em()->create(RouteFilter::class);
		return $this->routeFilterAddEdit($routeFilter);
	}

	protected function routeFilterSaveProcess(RouteFilter $routeFilter)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'find_route' => 'str',
			'replace_route' => 'str',
			'url_to_route_only' => 'str',
			'enabled' => 'bool',
		]);
		$form->basicEntitySave($routeFilter, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['route_filter_id'])
		{
			$routeFilter = $this->assertRouteFilterExists($params['route_filter_id']);
		}
		else
		{
			$routeFilter = $this->em()->create(RouteFilter::class);
		}

		$this->routeFilterSaveProcess($routeFilter)->run();

		return $this->redirect($this->buildLink('route-filters'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$routeFilter = $this->assertRouteFilterExists($params['route_filter_id']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$routeFilter,
			$this->buildLink('route-filters/delete', $routeFilter),
			$this->buildLink('route-filters/edit', $routeFilter),
			$this->buildLink('route-filters'),
			$routeFilter->find_route
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(RouteFilterFinder::class, 'enabled');
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return RouteFilter
	 */
	protected function assertRouteFilterExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(RouteFilter::class, $id, $with, $phraseKey);
	}

	/**
	 * @return RouteFilterRepository
	 */
	protected function getRouteFilterRepo()
	{
		return $this->repository(RouteFilterRepository::class);
	}
}
