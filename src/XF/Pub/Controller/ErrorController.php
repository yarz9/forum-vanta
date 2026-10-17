<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\ErrorPlugin;
use XF\Mvc\ParameterBag;

class ErrorController extends AbstractController
{
	public function actionDispatchError(ParameterBag $params)
	{
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

	public function assertIpNotBanned()
	{
	}

	public function assertNotBanned()
	{
	}

	public function assertViewingPermissions($action)
	{
	}

	public function assertCorrectVersion($action)
	{
	}

	public function assertBoardActive($action)
	{
	}

	public function assertTfaRequirement($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
	}

	public function assertPolicyAcceptance($action)
	{
	}
}
