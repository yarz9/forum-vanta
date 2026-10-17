<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\ErrorPlugin;
use XF\Mvc\ParameterBag;

class ErrorController extends AbstractController
{
	public function actionDispatchError(ParameterBag $params)
	{
		// if we got here and we're not logged in, we basically just need to force the login screen
		if (!\XF::visitor()->is_admin)
		{
			return $this->view('XF:Login\Form', 'login_form');
		}

		return $this->plugin(ErrorPlugin::class)->actionDispatchError($params);
	}

	public function actionException(ParameterBag $params)
	{
		return $this->plugin(ErrorPlugin::class)->actionException($params->get('exception', false));
	}

	public function actionAddOnUpgrade(ParameterBag $params)
	{
		return $this->plugin(ErrorPlugin::class)->actionAddOnUpgrade();
	}

	public function checkCsrfIfNeeded($action, ParameterBag $params)
	{
	}

	public function assertAdmin()
	{
	}

	public function assertCorrectVersion($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
	}
}
