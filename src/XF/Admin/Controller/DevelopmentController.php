<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\AdminSectionPlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;

class DevelopmentController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertDevelopmentMode();
	}

	public function actionIndex()
	{
		return $this->plugin(AdminSectionPlugin::class)->actionView('development');
	}
}
