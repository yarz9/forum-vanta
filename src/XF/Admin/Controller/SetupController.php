<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\AdminSectionPlugin;

class SetupController extends AbstractController
{
	public function actionIndex()
	{
		return $this->plugin(AdminSectionPlugin::class)->actionView('setup');
	}
}
